<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelChanged
{
    use Dispatchable, SerializesModels;

    public Model $model;
    public string $action;

    public function __construct(Model $model, string $action)
    {
        $this->model = $model;
        $this->action = $action;
    }
}
