<?php

namespace App\Livewire\Wizard;

use App\Core\Library\FileLibrary;
use App\Core\Library\UploadValidator;
use App\Core\Modules\InputRequirement;
use App\Core\Wizard\WizardStep;
use App\Models\LibraryFile;
use Livewire\WithFileUploads;

/**
 * Langkah wizard generik: memilih berkas masukan dari pustaka atau mengunggah baru.
 * Modul cukup menurunkan kelas ini dan menentukan peran berkas di roles().
 */
abstract class InputFilesStep extends WizardStep
{
    use WithFileUploads;

    public array $uploads = [];

    /** @return string[] peran berkas yang ditampilkan pada langkah ini */
    abstract protected function roles(): array;

    protected function title(): string
    {
        return 'Berkas masukan';
    }

    protected function intro(): string
    {
        return '';
    }

    /** Dipanggil setelah berkas sebuah peran berganti (mis. reset ekstraksi). */
    protected function changed(string $role): void {}

    /** @return array<string, InputRequirement> */
    protected function requirements(): array
    {
        $all = collect($this->module()->inputRequirements())->keyBy('role');

        return collect($this->roles())->mapWithKeys(fn ($r) => [$r => $all[$r]])->all();
    }

    public function updatedUploads($value, string $role): void
    {
        $this->resetErrorBag("uploads.$role");
        $req = $this->requirements()[$role] ?? null;
        $file = $this->uploads[$role] ?? null;
        if (! $req || ! $file) {
            return;
        }

        if ($error = app(UploadValidator::class)->check($file, $req->mimes)) {
            $this->addError("uploads.$role", $error);
            unset($this->uploads[$role]);

            return;
        }

        $session = $this->session();
        $stored = app(FileLibrary::class)->store($file, $role, auth()->user(), $session->module_key);
        unset($this->uploads[$role]);
        $this->choose($role, $stored->id);
        $this->toast("\"{$stored->original_name}\" diunggah dan dipilih.");
    }

    public function choose(string $role, ?int $fileId): void
    {
        abort_unless(array_key_exists($role, $this->requirements()), 422);
        $session = $this->session();

        if ($fileId) {
            $file = LibraryFile::availableTo(auth()->user())->where('role', $role)->findOrFail($fileId);
            $fileId = $file->id;
        }

        $this->resetErrorBag("uploads.$role");
        $inputs = $session->inputs ?? [];
        $before = $inputs[$role] ?? null;
        $inputs[$role] = $fileId;
        $session->inputs = array_filter($inputs);
        // Nama berkas berubah → salinan lama di Files API tidak lagi relevan.
        $session->options = array_merge($session->options ?? [], ['remote_files' => []]);
        $session->save();

        if ($before !== $fileId) {
            $this->changed($role);
        }
    }

    public function continue(): void
    {
        $this->resetErrorBag();
        $session = $this->session();
        foreach ($this->requirements() as $role => $req) {
            if ($req->required && empty($session->inputs[$role])) {
                $this->addError("uploads.$role", "{$req->label} wajib dipilih atau diunggah.");
            }
        }
        if ($this->getErrorBag()->isEmpty()) {
            $this->next();
        }
    }

    public function back(): void
    {
        $this->dispatch('wizard-back');
    }

    public function render()
    {
        $session = $this->session();
        $user = auth()->user();
        $reqs = $this->requirements();

        return view('livewire.wizard.input-files-step', [
            'title' => $this->title(),
            'intro' => $this->intro(),
            'requirements' => $reqs,
            'selected' => LibraryFile::withTrashed()->whereIn('id', array_filter($session->inputs ?? []))->get()->keyBy('id'),
            'inputs' => $session->inputs ?? [],
            'options' => LibraryFile::availableTo($user)->whereIn('role', array_keys($reqs))->latest()->get()->groupBy('role'),
        ]);
    }
}
