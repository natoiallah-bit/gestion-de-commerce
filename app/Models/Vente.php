<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Vente extends Model
{
    public const MODES = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile money',
    ];

    protected $fillable = [
        'numero', 'client_id', 'user_id', 'total', 'montant_paye', 'mode_paiement',
        'statut', 'annulee_par', 'annulee_le', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'montant_paye' => 'integer',
            'annulee_le' => 'datetime',
        ];
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteLigne::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function annuleePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par');
    }

    public function scopeValidees(Builder $query): Builder
    {
        return $query->where('statut', 'validee');
    }

    public function reste(): int
    {
        return $this->total - $this->montant_paye;
    }

    public function estAnnulee(): bool
    {
        return $this->statut === 'annulee';
    }

    /**
     * Enregistre une vente : prix lus en base (jamais depuis le navigateur),
     * stock vérifié et décrémenté sous verrou, le tout dans une transaction.
     *
     * @param  array<int, array{produit_id:int, quantite:int}>  $lignes
     */
    public static function enregistrer(array $lignes, int $montantPaye, string $mode, ?int $clientId, int $userId): self
    {
        return DB::transaction(function () use ($lignes, $montantPaye, $mode, $clientId, $userId) {
            // Regroupe les lignes d'un même produit
            $quantites = [];
            foreach ($lignes as $ligne) {
                $id = (int) $ligne['produit_id'];
                $quantites[$id] = ($quantites[$id] ?? 0) + (int) $ligne['quantite'];
            }

            $produits = Produit::whereIn('id', array_keys($quantites))->lockForUpdate()->get()->keyBy('id');

            $erreurs = [];
            $total = 0;
            foreach ($quantites as $id => $quantite) {
                $produit = $produits->get($id);
                if (! $produit || ! $produit->actif) {
                    $erreurs[] = 'Un produit du panier n\'est plus disponible.';
                } elseif ($produit->stock < $quantite) {
                    $erreurs[] = "Stock insuffisant pour « {$produit->nom} » (reste {$produit->stock}).";
                } else {
                    $total += $produit->prix_vente * $quantite;
                }
            }
            if ($erreurs) {
                throw ValidationException::withMessages(['lignes' => $erreurs]);
            }

            if ($montantPaye > $total) {
                $montantPaye = $total; // la monnaie rendue n'est pas un encaissement
            }
            if ($montantPaye < $total && ! $clientId) {
                throw ValidationException::withMessages([
                    'client_id' => 'Pour une vente à crédit (paiement incomplet), choisissez le client.',
                ]);
            }

            $vente = self::create([
                'numero' => 'TMP-'.uniqid(),
                'client_id' => $clientId,
                'user_id' => $userId,
                'total' => $total,
                'montant_paye' => $montantPaye,
                'mode_paiement' => $mode,
            ]);
            $vente->update(['numero' => 'V'.str_pad((string) $vente->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($quantites as $id => $quantite) {
                $produit = $produits->get($id);
                $vente->lignes()->create([
                    'produit_id' => $produit->id,
                    'designation' => $produit->nom,
                    'quantite' => $quantite,
                    'prix_unitaire' => $produit->prix_vente,
                    'prix_achat_unitaire' => $produit->prix_achat,
                    'total' => $produit->prix_vente * $quantite,
                ]);
                $produit->mouvementer(-$quantite, 'vente', $userId, ['vente_id' => $vente->id]);
            }

            return $vente;
        });
    }

    /**
     * Annule la vente et remet les articles en stock. La dette du client disparaît
     * d'elle-même puisqu'elle ne compte que les ventes validées.
     */
    public function annuler(int $userId, ?string $motif): void
    {
        DB::transaction(function () use ($userId, $motif) {
            $vente = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($vente->estAnnulee()) {
                return;
            }

            $ids = $vente->lignes()->pluck('produit_id');
            $produits = Produit::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            foreach ($vente->lignes as $ligne) {
                $produits[$ligne->produit_id]->mouvementer($ligne->quantite, 'annulation', $userId, ['vente_id' => $vente->id]);
            }

            $vente->update([
                'statut' => 'annulee',
                'annulee_par' => $userId,
                'annulee_le' => now(),
                'motif_annulation' => $motif,
            ]);
            $this->setRawAttributes($vente->getAttributes(), true);
        });
    }
}
