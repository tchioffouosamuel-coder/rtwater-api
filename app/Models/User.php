<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'password',
    ];
    // On retire 'role' car le rôle est maintenant dans une table séparée

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ═══════════════════════════════════════════════════
    // RELATIONS
    // ═══════════════════════════════════════════════════

    public function roles()
    {
        return $this->belongsToMany(Role::class);
        // Un User peut avoir PLUSIEURS Roles
        // Relation Many-to-Many via la table pivot role_user
        //
        // Utilisation :
        // $user->roles               → collection de tous ses rôles
        // $user->roles->first()->name → nom du premier rôle
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // ═══════════════════════════════════════════════════
    // MÉTHODES UTILITAIRES
    // ═══════════════════════════════════════════════════

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
        // Vérifie si cet user a UN rôle précis
        //
        // $this->roles → la collection de tous ses rôles
        // ->contains('name', $roleName)
        //    → cherche dans la collection un rôle dont name = $roleName
        //    → retourne true si trouvé, false sinon
        //
        // Utilisation :
        // $user->hasRole(Role::ADMIN)        → true/false
        // $user->hasRole(Role::GESTIONNAIRE) → true/false
        // $user->hasRole('custom_role')      → true/false
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->whereIn('name', $roleNames)->isNotEmpty();
        // Vérifie si l'user a AU MOINS UN des rôles listés
        //
        // ->whereIn('name', $roleNames)
        //    → filtre la collection : garde seulement les rôles dont
        //      le name est dans le tableau $roleNames
        // ->isNotEmpty()
        //    → retourne true si la collection filtrée n'est pas vide
        //
        // Utilisation :
        // $user->hasAnyRole([Role::ADMIN, Role::GESTIONNAIRE])
        // → true si l'user est admin OU gestionnaire
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
        // Raccourci pratique pour vérifier si l'user est admin
        // Utilisation : if ($user->isAdmin()) { ... }
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        // Cherche le rôle dans la DB par son nom

        if ($role && !$this->hasRole($roleName)) {
            $this->roles()->attach($role->id);
            // attach() → insère une ligne dans la table pivot role_user
            // INSERT INTO role_user (user_id, role_id) VALUES (1, 2)
            //
            // On vérifie d'abord !$this->hasRole($roleName)
            // pour éviter les doublons si le rôle est déjà attribué
        }
        // Utilisation :
        // $user->assignRole(Role::ADMIN)
        // $user->assignRole(Role::GESTIONNAIRE)
    }

    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();

        if ($role) {
            $this->roles()->detach($role->id);
            // detach() → supprime la ligne dans la table pivot
            // DELETE FROM role_user WHERE user_id = 1 AND role_id = 2
        }
        // Utilisation :
        // $user->removeRole(Role::GESTIONNAIRE)
    }
}
