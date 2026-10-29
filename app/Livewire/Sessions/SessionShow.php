<?php

namespace App\Livewire\Sessions;

use App\Core\Generation\DocumentRegenerator;
use App\Core\Generation\SessionLauncher;
use App\Core\Library\DocumentLibrary;
use App\Core\Modules\ModuleRegistry;
use App\Models\GeneratedDocument;
use App\Models\GenerationSession;
use App\Services\Audit;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Rincian sesi generate (generik untuk semua modul): dokumen per butir (mis. per MK),
 * dokumen tingkat sesi (mis. Silabus Prodi), versi, laporan pemeriksaan, unduhan,
 * generate ulang, perbaikan otomatis, dan progres langsung.
 */
class SessionShow extends Component
{
    #[Locked]
    public int $sessionId;

    public ?int $regenId = null;

    public string $instruction = '';

    public ?int $historyId = null;

    public function mount(GenerationSession $session): void
    {
        Gate::authorize('view', $session);
        $this->sessionId = $session->id;

        if ($session->user_id !== auth()->id()) {
            Audit::log('session.view', $session, ['owner_id' => $session->user_id]);
        }
    }

    private function session(): GenerationSession
    {
        return GenerationSession::findOrFail($this->sessionId);
    }

    private function document(int $id): GeneratedDocument
    {
        $doc = GeneratedDocument::where('generation_session_id', $this->sessionId)->findOrFail($id);
        Gate::authorize('update', $doc);

        return $doc;
    }

    public function cancel(SessionLauncher $launcher): void
    {
        $session = $this->session();
        Gate::authorize('update', $session);
        $n = $launcher->cancel($session);
        $this->dispatch('toast', type: 'info', message: "{$n} proses antre dibatalkan.");
    }

    public function toggleFinal(int $documentId, DocumentLibrary $library): void
    {
        $doc = $this->document($documentId);
        $library->markFinal($doc, ! $doc->is_final);
        $this->dispatch('toast', type: 'success', message: $doc->is_final ? 'Ditandai final dan masuk Pustaka Dokumen.' : 'Tanda final dihapus.');
    }

    public function openRegenerate(int $documentId): void
    {
        $this->document($documentId);
        $this->regenId = $documentId;
        $this->instruction = '';
        $this->resetValidation();
    }

    public function regenerate(DocumentRegenerator $regen): void
    {
        $this->validate(['instruction' => ['required', 'string', 'max:2000']], attributes: ['instruction' => 'instruksi tambahan']);
        try {
            $regen->regenerate($this->document($this->regenId), $this->instruction);
            $this->dispatch('toast', type: 'success', message: 'Generate ulang masuk antrean; versi baru akan muncul di sini.');
            $this->regenId = null;
        } catch (RuntimeException $e) {
            $this->addError('instruction', $e->getMessage());
        }
    }

    public function autofix(int $documentId, DocumentRegenerator $regen): void
    {
        try {
            $regen->autofix($this->document($documentId));
            $this->dispatch('toast', type: 'success', message: 'Perbaikan otomatis masuk antrean.');
        } catch (RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function showHistory(?int $documentId): void
    {
        $this->historyId = $documentId;
    }

    public function render(DocumentRegenerator $regen)
    {
        $session = GenerationSession::with(['user', 'prodi', 'items', 'tasks'])->findOrFail($this->sessionId);
        $registry = app(ModuleRegistry::class);
        $module = $registry->has($session->module_key) ? $registry->get($session->module_key) : null;
        $docs = $session->documents()->orderByDesc('version')->get();
        $latest = $docs->unique(fn ($d) => $d->generation_item_id.'|'.$d->doc_type);
        $history = $this->historyId ? $docs->firstWhere('id', $this->historyId) : null;

        $tasksByItem = $session->tasks->groupBy('generation_item_id');
        $items = $session->items->filter(fn ($i) => $i->selected || $tasksByItem->has($i->id) || $latest->contains('generation_item_id', $i->id));

        return view('livewire.sessions.session-show', [
            'session' => $session,
            'moduleDef' => $module,
            'docTypes' => $module?->documentTypes() ?? [],
            'readOnly' => $session->user_id !== auth()->id(),
            'items' => $items,
            'latest' => $latest,
            'tasksByItem' => $tasksByItem,
            'sessionDocs' => $latest->whereNull('generation_item_id'),
            'sessionTasks' => $tasksByItem->get('', collect()),
            'history' => $history ? $docs->where('generation_item_id', $history->generation_item_id)->where('doc_type', $history->doc_type)->values() : collect(),
            'canAutofix' => fn (GeneratedDocument $d) => $regen->autofixAllowed($d),
            'pendingAi' => $docs->contains(fn ($d) => $d->status !== GeneratedDocument::STATUS_OK),
        ])->title($session->title ?: 'Sesi #'.$session->id);
    }
}
