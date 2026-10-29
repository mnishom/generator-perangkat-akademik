<?php

namespace App\Livewire\Admin;

use App\Actions\ChangeAccountStatus;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\AccountStatusLog;
use App\Models\Prodi;
use App\Models\UsageLimit;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Pengguna')]
class Users extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $prodi = '';

    public ?int $editingId = null;

    public array $form = [];

    public function updating($name): void
    {
        if (in_array($name, ['search', 'role', 'status', 'prodi'], true)) {
            $this->resetPage();
        }
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $limit = UsageLimit::forScope(UsageLimit::SCOPE_USER, null, $user->id);

        $this->editingId = $user->id;
        $this->form = [
            'role' => $user->role->value,
            'prodi_id' => $user->prodi_id,
            'prodi_locked' => $user->prodi_locked,
            'status' => $user->status->value,
            'status_reason' => $user->status_reason,
            'status_until' => $user->status_until?->format('Y-m-d\TH:i'),
            'max_generate_per_day' => $limit->max_generate_per_day,
            'max_generate_per_month' => $limit->max_generate_per_month,
            'max_tokens_per_month' => $limit->max_tokens_per_month,
        ];
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->form = [];
    }

    public function save(ChangeAccountStatus $changeStatus): void
    {
        $user = User::findOrFail($this->editingId);

        $data = $this->validate([
            'form.role' => ['required', Rule::enum(UserRole::class)],
            'form.prodi_id' => ['nullable', 'integer', 'exists:prodis,id'],
            'form.prodi_locked' => ['boolean'],
            'form.status' => ['required', Rule::enum(AccountStatus::class)],
            'form.status_reason' => ['nullable', 'string', 'max:255'],
            'form.status_until' => ['nullable', 'date', 'after:now'],
            'form.max_generate_per_day' => ['nullable', 'integer', 'min:0'],
            'form.max_generate_per_month' => ['nullable', 'integer', 'min:0'],
            'form.max_tokens_per_month' => ['nullable', 'integer', 'min:0'],
        ], attributes: [
            'form.status_until' => 'tanggal berakhir',
        ])['form'];

        $role = UserRole::from($data['role']);
        $status = AccountStatus::from($data['status']);

        if ($user->is(auth()->user()) && ($role !== UserRole::Admin || $status !== AccountStatus::Aktif)) {
            $this->addError('form.role', 'Anda tidak dapat menurunkan peran atau menonaktifkan akun Anda sendiri.');

            return;
        }

        $changes = [];
        if ($user->role !== $role) {
            $changes['role'] = [$user->role->value, $role->value];
            $user->role = $role;
        }
        if ($user->prodi_id !== ($data['prodi_id'] ? (int) $data['prodi_id'] : null) || $user->prodi_locked !== (bool) $data['prodi_locked']) {
            $changes['prodi'] = ['prodi_id' => $data['prodi_id'], 'locked' => (bool) $data['prodi_locked']];
            $user->prodi_id = $data['prodi_id'] ?: null;
            $user->prodi_locked = (bool) $data['prodi_locked'];
        }
        $user->save();

        if ($changes) {
            Audit::log('user.update', $user, $changes);
        }

        $until = $data['status_until'] ? Carbon::parse($data['status_until']) : null;
        $reason = $data['status_reason'] ?: null;
        $statusChanged = $user->status !== $status
            || ($status !== AccountStatus::Aktif && ($user->status_reason !== $reason || $user->status_until?->toDateTimeString() !== $until?->toDateTimeString()));
        if ($statusChanged) {
            $changeStatus($user, $status, $reason, $until);
        }

        $limit = UsageLimit::forScope(UsageLimit::SCOPE_USER, null, $user->id);
        $limitValues = [
            'max_generate_per_day' => $this->nullableInt($data['max_generate_per_day']),
            'max_generate_per_month' => $this->nullableInt($data['max_generate_per_month']),
            'max_tokens_per_month' => $this->nullableInt($data['max_tokens_per_month']),
        ];
        if ($limit->exists || array_filter($limitValues, fn ($v) => $v !== null)) {
            $limit->fill($limitValues)->save();
            Audit::log('usage_limit.user', $user, $limitValues);
        }

        $this->cancel();
        $this->dispatch('toast', type: 'success', message: 'Data pengguna tersimpan.');
    }

    private function nullableInt(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    public function render()
    {
        $users = User::query()
            ->with('prodi')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->role, fn ($q) => $q->where('role', $this->role))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->prodi, fn ($q) => $q->where('prodi_id', $this->prodi))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.users', [
            'users' => $users,
            'prodis' => Prodi::orderBy('nama')->get(),
            'editing' => $this->editingId ? User::with('prodi')->find($this->editingId) : null,
            'statusLogs' => $this->editingId
                ? AccountStatusLog::with('changer')->where('user_id', $this->editingId)->latest()->limit(5)->get()
                : collect(),
        ]);
    }
}
