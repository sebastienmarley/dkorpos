<?php

namespace App\Models;

use App\Enums\CustomerNoticeStatus;
use App\Enums\CustomerNoticeType;
use Database\Factories\CustomerNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Avis envoyé (ou qui aurait dû l'être) à un client : l'historique que le service à la clientèle consulte sur la
 * commande. Un avis automatique a une clé d'événement unique : le même événement n'envoie jamais deux avis.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $customer_order_id
 * @property CustomerNoticeType $type
 * @property string|null $event_key Clé unique de l'événement (ex. « delivery-scheduled:12 ») ; vide pour un envoi manuel.
 * @property string $channel
 * @property string|null $recipient Courriel au moment de l'envoi.
 * @property CustomerNoticeStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property string|null $subject_type
 * @property int|null $subject_id Document dont l'avis parle (livraison, rendez-vous…).
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read customer $customer
 * @property-read CustomerOrder|null $order
 * @property-read User|null $creator
 * @property-read Model|null $subject
 */
#[Fillable(['customer_id', 'customer_order_id', 'type', 'event_key', 'channel', 'recipient', 'status', 'sent_at', 'failed_at', 'created_by', 'subject_type', 'subject_id'])]
class CustomerNotice extends Model
{
    /** @use HasFactory<CustomerNoticeFactory> */
    use HasFactory;

    protected $attributes = [
        'channel' => 'mail',
    ];

    protected $casts = [
        'type' => CustomerNoticeType::class,
        'status' => CustomerNoticeStatus::class,
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /** @return BelongsTo<customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(customer::class);
    }

    /** @return BelongsTo<CustomerOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Un avis échoué se renvoie, tant que le document dont il parle existe encore (un rendez-vous supprimé, non). */
    public function isResendable(): bool
    {
        return $this->status === CustomerNoticeStatus::Failed
            && ($this->subject_type === null || $this->subject()->exists());
    }
}
