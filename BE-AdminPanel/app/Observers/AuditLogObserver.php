<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogObserver
{
    protected $ignoreAttributes = ['updated_at', 'created_at', 'remember_token'];

    public function created($model)
    {
        $this->log('created', $model, null, $model->getAttributes());
    }

    public function updated($model)
    {
        $dirty = $model->getDirty();
        foreach ($this->ignoreAttributes as $attr) {
            unset($dirty[$attr]);
        }
        if (empty($dirty)) return;

        $original = [];
        foreach ($dirty as $key => $value) {
            $original[$key] = $model->getOriginal($key);
        }

        $this->log('updated', $model, $original, $dirty);
    }

    public function deleted($model)
    {
        $this->log('deleted', $model, $model->getAttributes(), null);
    }

    protected function log($action, $model, $oldValues, $newValues)
    {
        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'entity_type' => class_basename($model),
            'entity_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'description' => $this->buildDescription($action, $model),
        ]);
    }

    protected function buildDescription($action, $model)
    {
        $user = auth()->user();
        $name = $user ? $user->name : 'System';
        $entity = class_basename($model);
        $id = $model->getKey();

        $label = $model->name ?? $model->title ?? $model->license ?? $model->email ?? "#{$id}";

        return "{$name} {$action} {$entity} [{$label}] (ID: {$id})";
    }
}
