<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(protected AuditService $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $items = User::query()
            ->with('roles')
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%')->orWhere('email', 'like', '%'.$s.'%'))
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return UserResource::collection($items);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'active' => $request->boolean('active', true),
        ]);
        $user->syncRoles($roles);

        $this->audit->record('user.created', $user, [], $user->only(['id', 'name', 'email', 'active']) + ['roles' => $roles], null, $request->user());

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $before = $user->only(['id', 'name', 'email', 'active']) + ['roles' => $user->getRoleNames()->all()];

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ]));
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($request->has('active')) {
            $user->active = $request->boolean('active');
        }
        $user->save();

        if ($roles !== null) {
            $user->syncRoles($roles);
        }

        $this->audit->record('user.updated', $user, $before, $user->only(['id', 'name', 'email', 'active']) + ['roles' => $user->getRoleNames()->all()], null, $request->user());

        return new UserResource($user->fresh('roles'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->createdMovements()->exists()) {
            throw OperationException::make('Pengguna memiliki riwayat pergerakan. Nonaktifkan saja daripada menghapus.');
        }

        $before = $user->only(['id', 'name', 'email', 'active']);
        $user->delete();
        $this->audit->record('user.deleted', null, $before, [], null, $request->user());

        return response()->json(['message' => 'Pengguna dihapus.']);
    }
}
