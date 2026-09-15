<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DriverReadiness extends Model
{
    protected $table = 'driver_readiness';
    protected $guarded = ['id'];
    protected $hidden = ['sim_path', 'health_path', 'skck_path'];

    public function summary(): array
    {
        $data = $this->toArray();
        $data['documents'] = [];
        foreach (['sim', 'health', 'skck'] as $kind) {
            $date = $this->{$kind . '_valid_until'};
            $data['documents'][$kind] = [
                'uploaded' => (bool) $this->{$kind . '_path'},
                'valid' => (bool) $this->{$kind . '_path'} && $date && $date >= now()->toDateString(),
            ];
        }
        $data['ready'] = $this->health_status === 'healthy' && (bool) $this->sim_number
            && collect($data['documents'])->every(fn ($document) => $document['valid']);
        return $data;
    }
}
