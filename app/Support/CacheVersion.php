<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache invalidable par groupe, compatible avec tous les drivers (file, database…)
 * qui ne gèrent pas les « tags ».
 *
 * Chaque groupe (pages, blog, categories) a un numéro de version inclus dans les clés.
 * Modifier un contenu incrémente la version : toutes les anciennes entrées deviennent
 * inaccessibles d'un coup et expirent seules.
 */
class CacheVersion
{
    public static function key(string $group, string ...$parts): string
    {
        $version = Cache::rememberForever("cv:{$group}", fn() => 1);

        return $group . ':v' . $version . ':' . implode(':', $parts);
    }

    /** Lit ou calcule une valeur mise en cache pour ce groupe. */
    public static function remember(string $group, array $parts, int $seconds, \Closure $callback): mixed
    {
        return Cache::remember(self::key($group, ...$parts), $seconds, $callback);
    }

    public static function bump(string ...$groups): void
    {
        foreach ($groups as $group) {
            if (!Cache::has("cv:{$group}")) {
                Cache::forever("cv:{$group}", 1);
            }
            Cache::increment("cv:{$group}");
        }
    }

    /** À appeler dans Model::booted() : toute écriture invalide les groupes indiqués. */
    public static function bustOnWrite(string $modelClass, string ...$groups): void
    {
        $bump = fn() => self::bump(...$groups);
        $modelClass::saved($bump);
        $modelClass::deleted($bump);
    }
}
