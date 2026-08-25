<?php

namespace App\Livewire;

use App\Exceptions\ClipSubmissionRefused;
use App\Models\Campaign;
use App\Services\Clips\ClipSubmissionService;
use Livewire\Component;

class SubmitClip extends Component
{
    public Campaign $campaign;

    public string $url = '';

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function submit(ClipSubmissionService $clips): void
    {
        $this->validate([
            'url' => ['required', 'string', 'max:2048'],
        ], [
            'url.required' => 'Collez le lien de votre publication.',
        ]);

        try {
            $clips->submit($this->campaign, auth()->user(), $this->url);
        } catch (ClipSubmissionRefused $exception) {
            $this->addError('url', $exception->getMessage());

            return;
        }

        $this->reset('url');

        session()->flash('status', 'Clip soumis. Il sera vérifié puis validé par la modération avant d\'être rémunéré.');

        $this->redirect(route('campaigns.show', $this->campaign), navigate: true);
    }

    public function render()
    {
        return view('livewire.submit-clip');
    }
}
