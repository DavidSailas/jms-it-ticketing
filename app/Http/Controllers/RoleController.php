<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\Activity;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Super admins: create, edit and delete roles, and choose what each role may do. */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->orderByRaw("CASE slug WHEN 'super_admin' THEN 0 WHEN 'admin' THEN 1 WHEN 'it_support' THEN 2 WHEN 'user' THEN 3 ELSE 4 END")
            ->orderBy('name')->get();

        foreach ($roles as $role) {
            $role->setAttribute('members_count', $role->memberQuery()->count());
        }

        return view('roles.index', ['roles' => $roles, 'catalog' => Permissions::all()]);
    }

    public function create()
    {
        return view('roles.form', $this->formData(new Role(['base_role' => 'admin', 'permissions' => []])));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:60', Rule::unique('roles', 'name')],
            'description' => 'nullable|string|max:200',
            'base_role'   => ['required', Rule::in(array_keys(Role::BASES))],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
        ], [
            'name.required' => 'Give the role a name.',
            'name.unique'   => 'A role with this name already exists.',
            'base_role.required' => 'Choose what kind of account this role is for.',
        ]);

        $role = Role::create([
            'slug'        => $this->uniqueSlug($data['name']),
            'name'        => trim($data['name']),
            'description' => $data['description'] ?? null,
            'base_role'   => $data['base_role'],
            'permissions' => $this->allowed($data['base_role'], $data['permissions'] ?? []),
            'is_system'   => false,
        ]);

        Activity::record($request->user(), 'role_created', "Created the role {$role->name}");

        return redirect()->route('roles.edit', $role)->with('success', "Role \"{$role->name}\" was created. You can now add people to it.");
    }

    public function edit(Role $role)
    {
        return view('roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role)
    {
        if ($role->isLocked()) {
            return back()->with('error', "The {$role->name} role cannot be changed.");
        }

        $rules = [
            'description' => 'nullable|string|max:200',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
        ];
        if (! $role->is_system) {
            $rules['name'] = ['required', 'string', 'min:2', 'max:60', Rule::unique('roles', 'name')->ignore($role->id)];
        }

        $data = $request->validate($rules, [
            'name.required' => 'Give the role a name.',
            'name.unique'   => 'A role with this name already exists.',
        ]);

        $before = collect($role->permissions ?? []);
        $after  = collect($this->allowed($role->base_role, $data['permissions'] ?? []));

        $role->update([
            'name'        => $role->is_system ? $role->name : trim($data['name']),
            'description' => $data['description'] ?? null,
            'permissions' => $after->all(),
        ]);

        $added   = $after->diff($before)->count();
        $removed = $before->diff($after)->count();
        Activity::record($request->user(), 'role_updated', "Updated the role {$role->name}" . ($added || $removed ? " (+{$added} / -{$removed} permissions)" : ''));

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" was saved.");
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', "The built-in {$role->name} role cannot be deleted.");
        }

        $members = $role->memberQuery()->count();
        if ($members > 0) {
            return back()->with('error', "{$role->name} still has {$members} " . Str::plural('person', $members) . '. Remove them from the role first.');
        }

        $name = $role->name;
        $role->delete();
        Activity::record($request->user(), 'role_deleted', "Deleted the role {$name}");

        return redirect()->route('roles.index')->with('success', "Role \"{$name}\" was deleted.");
    }

    /** Put one person into a custom role. They must already be the same kind of account (admin or IT engineer). */
    public function addMember(Request $request, Role $role)
    {
        abort_if($role->is_system, 404);

        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', $role->base_role)]], [
            'user_id.required' => 'Choose a person.',
            'user_id.exists'   => "Only {$role->baseLabel()} accounts can join this role.",
        ]);

        $user = User::findOrFail($data['user_id']);
        $user->update(['role_id' => $role->id]);

        Activity::record($request->user(), 'role_updated', "Gave {$user->name} the role {$role->name}");

        return back()->with('success', "{$user->name} now has the {$role->name} role.");
    }

    /** Take someone out of a custom role. They go back to the standard role for their account type. */
    public function removeMember(Request $request, Role $role, User $user)
    {
        abort_if($role->is_system, 404);
        abort_unless((int) $user->role_id === $role->id, 404);

        $user->update(['role_id' => null]);

        Activity::record($request->user(), 'role_updated', "Removed {$user->name} from the role {$role->name}");

        return back()->with('success', "{$user->name} was removed from {$role->name}.");
    }

    private function formData(Role $role): array
    {
        $members    = $role->exists && ! $role->is_system ? $role->memberQuery()->orderBy('name')->get() : collect();
        $candidates = $role->exists && ! $role->is_system
            ? User::query()->where('role', $role->base_role)->where(fn ($q) => $q->whereNull('role_id')->orWhere('role_id', '!=', $role->id))->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return [
            'role'       => $role,
            'groups'     => Permissions::GROUPS,
            'byBase'     => collect(array_keys(Role::BASES))->mapWithKeys(fn ($b) => [$b => Permissions::forBase($b)])->all()
                + ['super_admin' => Permissions::keys(), 'user' => []],
            'members'    => $members,
            'candidates' => $candidates,
        ];
    }

    /** Only keep permissions that make sense for this kind of account. */
    private function allowed(string $base, array $requested): array
    {
        return array_values(array_intersect(Permissions::forBase($base), $requested));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $slug = $base;
        $i = 2;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }

        return $slug;
    }
}
