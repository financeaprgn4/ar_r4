<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteMutasiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $id, $cabang, $taskId;

    public function __construct($id, $cabang, $taskId)
    {
        $this->id = $id;
        $this->cabang = $cabang;
        $this->taskId = $taskId;
    }

    public function handle()
    {
        app(StatementController::class)->runDeleteMutasi(
            $this->id,
            $this->cabang,
            $this->taskId
        );
    }
}

