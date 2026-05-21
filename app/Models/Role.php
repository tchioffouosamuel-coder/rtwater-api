<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    // ═══════════════════════════════════════════════════
    // CONSTANTES — les rôles prédéfinis
    // ═══════════════════════════════════════════════════

    const ADMIN       = 'admin';
    const GESTIONNAIRE = 'gestionnaire';
    const SECRETAIRE  = 'secretaire';
    const COMPTABLE   = 'comptable';
    const USER        = 'user';

    // Une constante c'est une valeur FIXE qui ne change jamais
    // const NOM = 'valeur';
    //
    // Pourquoi des constantes ?
    //
    // SANS constantes (dangereux) :
    // if ($user->hasRole('admin')) { ... }
    // if ($user->hasRole('Admin')) { ... }  ← faute de casse → bug silencieux !
    // if ($user->hasRole('admni')) { ... }  ← faute de frappe → bug silencieux !
    //
    // AVEC constantes (sécurisé) :
    // if ($user->hasRole(Role::ADMIN)) { ... }
    // → Si tu fais une faute : Role::ADIMN → PHP lève une ERREUR immédiatement
    // → Ton éditeur VS Code propose l'autocomplétion → pas de faute possible
    // → Si tu renommes 'admin' en 'administrator' → tu changes juste la constante
    //    et tout le reste du code s'adapte automatiquement
    //
    // Accès depuis n'importe où :
    // Role::ADMIN        → 'admin'
    // Role::GESTIONNAIRE → 'gestionnaire'
    // Role::USER         → 'user'

    // ═══════════════════════════════════════════════════
    // CONFIGURATION ELOQUENT
    // ═══════════════════════════════════════════════════

    protected $fillable = [
        'name',
        'label',
        'description',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            // MySQL stocke 0/1, Laravel convertit en true/false
        ];
    }

    // ═══════════════════════════════════════════════════
    // RELATIONS
    // ═══════════════════════════════════════════════════

    public function users()
    {
        return $this->belongsToMany(User::class);
        // Un Role APPARTIENT À PLUSIEURS Users
        // Et un User peut avoir PLUSIEURS Roles
        // → Relation Many-to-Many via la table pivot role_user
        //
        // Laravel déduit automatiquement :
        // Table pivot : role_user (ordre alphabétique : r avant u)
        // Clés        : role_id + user_id
        //
        // Utilisation :
        // $role->users          → tous les users avec ce rôle
        // $role->users->count() → nombre d'users avec ce rôle
    }

    // ═══════════════════════════════════════════════════
    // MÉTHODES UTILITAIRES
    // ═══════════════════════════════════════════════════

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
        // Retourne le rôle par défaut (USER)
        // static:: → appel sur la classe elle-même (pas sur une instance)
        // ?self    → retourne soit un Role, soit null si aucun rôle par défaut
        //
        // Utilisation :
        // $defaultRole = Role::getDefault();
        // → retourne le rôle 'user'
    }

    public static function getPredefined(): array
    {
        return [
            self::ADMIN,
            self::GESTIONNAIRE,
            self::SECRETAIRE,
            self::COMPTABLE,
            self::USER,
        ];
        // Retourne la liste des rôles prédéfinis
        // Utilisation pour vérifier si un rôle est prédéfini :
        // in_array('admin', Role::getPredefined()) → true
        // in_array('custom', Role::getPredefined()) → false
    }

    public function isPredefined(): bool
    {
        return in_array($this->name, self::getPredefined());
        // Vérifie si CE rôle est un rôle prédéfini
        // Utilisation :
        // $role->isPredefined() → true si c'est admin/gestionnaire/etc.
        //
        // Utile pour empêcher la suppression des rôles système :
        // if ($role->isPredefined()) {
        //     return response()->json(['message' => 'Impossible de supprimer un rôle système'], 403);
        // }
    }
}
