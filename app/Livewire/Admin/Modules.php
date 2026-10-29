<?php

namespace App\Livewire\Admin;

use App\Core\Modules\ModuleRegistry;
use App\Models\ModuleAccessRule;
use App\Models\ModuleState;
use App\Models\Prodi;
use App\Models\UsageLimit;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Modul')]
class Modules extends Component
{
    public array $states = [];

    public array $limits = [];

    public array $rule = ['module_key' => '', 'scope' => 'prodi', 'scope_id' => '', 'allowed' => '0'];

    public function mount(ModuleRegistry $registry): void
    {
        foreach ($registry->all() as $key => $module) {
            $state = ModuleState::for($key);
            $this->states[$key] = [
                'enabled' => $state->enabled,
                'generate_open' => $state->generate_open,
                'model' => $state->model ?? '',
            ];
            $limit = UsageLimit::forScope(UsageLimit::SCOPE_MODULE, $key);
            $this->limits[$key] = [
                'max_generate_per_day' => $limit->max_generate_per_day,
                'max_generate_per_month' => $limit->max_generate_per_month,
                'max_tokens_per_month' => $limit->max_tokens_per_month,
            ];
        }
        $this->rule['module_key'] = array_key_first($registry->all()) ?? '';
    }

    public function save(string $key, ModuleRegistry $registry): void
    {
        abort_unless($registry->has($key), 404);

        $data = $this->validate([
            "states.$key.enabled" => ['boolean'],
            "states.$key.generate_open" => ['boolean'],
            "states.$key.model" => ['nullable', Rule::in(array_keys(config('claude.models')))],
            "limits.$key.max_generate_per_day" => ['nullable', 'integer', 'min:0'],
            "limits.$key.max_generate_per_month" => ['nullable', 'integer', 'min:0'],
            "limits.$key.max_tokens_per_month" => ['nullable', 'integer', 'min:0'],
        ]);

        $state = ModuleState::for($key);
        $state->fill([
            'enabled' => (bool) $data['states'][$key]['enabled'],
            'generate_open' => (bool) $data['states'][$key]['generate_open'],
            'model' => $data['states'][$key]['model'] ?: null,
        ])->save();

        $limit = array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, $data['limits'][$key] ?? []);
        UsageLimit::forScope(UsageLimit::SCOPE_MODULE, $key)->fill($limit)->save();

        Audit::log('module.update', $state, ['state' => $state->only('enabled', 'generate_open', 'model'), 'limit' => $limit]);
        $this->dispatch('toast', type: 'success', message: 'Pengaturan modul tersimpan.');
    }

    public function addRule(ModuleRegistry $registry): void
    {
        $data = $this->validate([
            'rule.module_key' => ['required', Rule::in(array_keys($registry->all()))],
            'rule.scope' => ['required', Rule::in([ModuleAccessRule::SCOPE_PRODI, ModuleAccessRule::SCOPE_USER])],
            'rule.scope_id' => ['required', 'integer', $this->rule['scope'] === 'user' ? 'exists:users,id' : 'exists:prodis,id'],
            'rule.allowed' => ['required', 'boolean'],
        ], attributes: ['rule.scope_id' => 'prodi/pengguna'])['rule'];

        $rule = ModuleAccessRule::updateOrCreate(
            ['module_key' => $data['module_key'], 'scope' => $data['scope'], 'scope_id' => (int) $data['scope_id']],
            ['allowed' => (bool) $data['allowed']],
        );
        Audit::log('module.access', $rule, $rule->only('module_key', 'scope', 'scope_id', 'allowed'));
        $this->rule['scope_id'] = '';
        $this->dispatch('toast', type: 'success', message: 'Aturan akses tersimpan.');
    }

    public function removeRule(int $id): void
    {
        $rule = ModuleAccessRule::findOrFail($id);
        Audit::log('module.access.remove', $rule, $rule->only('module_key', 'scope', 'scope_id', 'allowed'));
        $rule->delete();
    }

    public function render(ModuleRegistry $registry)
    {
        $rules = ModuleAccessRule::orderBy('module_key')->orderBy('scope')->get();

        return view('livewire.admin.modules', [
            'modules' => $registry->all(),
            'rules' => $rules,
            'prodis' => Prodi::orderBy('nama')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'prodiNames' => Prodi::whereIn('id', $rules->where('scope', 'prodi')->pluck('scope_id'))->get()->keyBy('id'),
            'userNames' => User::whereIn('id', $rules->where('scope', 'user')->pluck('scope_id'))->get()->keyBy('id'),
            'models' => config('claude.models'),
        ]);
    }
}
