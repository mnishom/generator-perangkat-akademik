<?php

namespace App\Livewire\Admin;

use App\Core\Generation\GenerationRunner;
use App\Core\Modules\ModuleRegistry;
use App\Models\AiUsage;
use App\Models\GenerationTask;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dashboard Pemakaian')]
class UsageDashboard extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $group = 'module'; // module | user | prodi | day

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function cancelTask(int $id, GenerationRunner $runner): void
    {
        $task = GenerationTask::whereIn('status', [GenerationTask::STATUS_QUEUED, GenerationTask::STATUS_WAITING])->findOrFail($id);
        $runner->finish($task, GenerationTask::STATUS_CANCELLED, 'Dibatalkan admin.');
        Audit::log('task.cancel', $task, ['session_id' => $task->generation_session_id]);
        $this->dispatch('toast', type: 'info', message: 'Job dibatalkan.');
    }

    public function cancelAllQueued(GenerationRunner $runner): void
    {
        $tasks = GenerationTask::whereIn('status', [GenerationTask::STATUS_QUEUED, GenerationTask::STATUS_WAITING])->get();
        foreach ($tasks as $task) {
            $runner->finish($task, GenerationTask::STATUS_CANCELLED, 'Dibatalkan admin.');
        }
        Audit::log('task.cancel_all', null, ['count' => $tasks->count()]);
        $this->dispatch('toast', type: 'info', message: $tasks->count().' Job antre dibatalkan.');
    }

    public function render(ModuleRegistry $registry)
    {
        $base = AiUsage::query()
            ->whereDate('ai_usages.created_at', '>=', $this->from)
            ->whereDate('ai_usages.created_at', '<=', $this->to);

        $totals = (clone $base)->selectRaw("
            sum(case when kind = 'generate' then 1 else 0 end) as generates,
            sum(case when kind = 'extract' then 1 else 0 end) as extracts,
            coalesce(sum(input_tokens + cache_read_tokens + cache_write_tokens), 0) as input_tokens,
            coalesce(sum(output_tokens), 0) as output_tokens,
            coalesce(sum(cost_usd), 0) as cost")->first();

        [$keyExpr, $join, $label] = match ($this->group) {
            'user' => ['ai_usages.user_id', fn ($q) => $q->leftJoin('users', 'users.id', '=', 'ai_usages.user_id'), DB::raw('max(users.name) as label')],
            'prodi' => ['ai_usages.prodi_id', fn ($q) => $q->leftJoin('prodis', 'prodis.id', '=', 'ai_usages.prodi_id'), DB::raw('max(prodis.nama) as label')],
            'day' => [DB::raw('date(ai_usages.created_at)'), fn ($q) => $q, DB::raw('date(ai_usages.created_at) as label')],
            default => ['ai_usages.module_key', fn ($q) => $q, DB::raw('ai_usages.module_key as label')],
        };

        $rows = $join(clone $base)
            ->select($label)
            ->selectRaw("sum(case when kind = 'generate' then 1 else 0 end) as generates")
            ->selectRaw('coalesce(sum(input_tokens + cache_read_tokens + cache_write_tokens), 0) as input_tokens')
            ->selectRaw('coalesce(sum(output_tokens), 0) as output_tokens')
            ->selectRaw('coalesce(sum(cost_usd), 0) as cost')
            ->groupBy($keyExpr)
            ->orderByDesc('cost')
            ->get();

        $modules = $registry->all();
        if ($this->group === 'module') {
            $rows->each(fn ($r) => $r->label = ($modules[$r->label] ?? null)?->name() ?? ($r->label ?: 'Lainnya (smoke test)'));
        }

        return view('livewire.admin.usage-dashboard', [
            'totals' => $totals,
            'rows' => $rows,
            'failed' => GenerationTask::with('session.user')->whereIn('status', [GenerationTask::STATUS_FAILED, GenerationTask::STATUS_BLOCKED])
                ->whereDate('updated_at', '>=', $this->from)->latest('updated_at')->limit(20)->get(),
            'queued' => GenerationTask::with('session.user')->whereIn('status', [GenerationTask::STATUS_QUEUED, GenerationTask::STATUS_WAITING, GenerationTask::STATUS_RUNNING])->oldest()->get(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'pendingJobs' => DB::table('jobs')->count(),
        ]);
    }
}
