<?php

namespace App\Livewire\Admin;

use App\Models\UsageLimit;
use App\Services\Audit;
use App\Services\Settings;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan Sistem')]
class SystemSettings extends Component
{
    public array $form = [];

    public array $limit = [];

    public function mount(Settings $settings): void
    {
        $s = $settings->all();
        $this->form = [
            'generate_enabled' => (bool) $s['generate_enabled'],
            'generate_closed_message' => (string) $s['generate_closed_message'],
            'max_upload_mb' => (int) $s['max_upload_mb'],
            'max_concurrent_jobs' => (int) $s['max_concurrent_jobs'],
            'delete_remote_files' => (bool) $s['delete_remote_files'],
            'autofix_max' => (int) $s['autofix_max'],
            'default_model' => $settings->defaultModel(),
        ];

        $l = UsageLimit::forScope(UsageLimit::SCOPE_GLOBAL);
        $this->limit = [
            'max_generate_per_day' => $l->max_generate_per_day,
            'max_generate_per_month' => $l->max_generate_per_month,
            'max_tokens_per_month' => $l->max_tokens_per_month,
        ];
    }

    public function save(Settings $settings): void
    {
        $data = $this->validate([
            'form.generate_enabled' => ['boolean'],
            'form.generate_closed_message' => ['required', 'string', 'max:255'],
            'form.max_upload_mb' => ['required', 'integer', 'min:1', 'max:200'],
            'form.max_concurrent_jobs' => ['required', 'integer', 'min:1', 'max:10'],
            'form.delete_remote_files' => ['boolean'],
            'form.autofix_max' => ['required', 'integer', 'min:0', 'max:5'],
            'form.default_model' => ['required', Rule::in(array_keys(config('claude.models')))],
            'limit.max_generate_per_day' => ['nullable', 'integer', 'min:0'],
            'limit.max_generate_per_month' => ['nullable', 'integer', 'min:0'],
            'limit.max_tokens_per_month' => ['nullable', 'integer', 'min:0'],
        ]);

        $form = $data['form'];
        $form['generate_enabled'] = (bool) $form['generate_enabled'];
        $form['delete_remote_files'] = (bool) $form['delete_remote_files'];

        $before = $settings->all();
        $settings->setMany($form);
        Audit::log('settings.update', null, [
            'changes' => collect($form)->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v)->all(),
        ]);

        $limit = array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, $data['limit'] ?? []);
        UsageLimit::forScope(UsageLimit::SCOPE_GLOBAL)->fill($limit)->save();
        Audit::log('usage_limit.global', null, $limit);

        $this->dispatch('toast', type: 'success', message: 'Pengaturan tersimpan.');
    }

    public function render()
    {
        return view('livewire.admin.system-settings', ['models' => config('claude.models')]);
    }
}
