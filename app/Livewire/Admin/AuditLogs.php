<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Audit Log')]
class AuditLogs extends Component
{
    use WithPagination;

    #[Url]
    public string $action = '';

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.audit-logs', [
            'logs' => AuditLog::with('actor')
                ->when($this->action, fn ($q) => $q->where('action', 'like', $this->action.'%'))
                ->latest('id')
                ->paginate(30),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
