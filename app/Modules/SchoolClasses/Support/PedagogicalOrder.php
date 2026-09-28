<?php

namespace Modules\SchoolClasses\Support;

/**
 * Rang pédagogique deviné d'après le libellé (« CE1 », « 6ème », « Tle D »…).
 * Ne sert qu'à proposer un premier rangement : l'ordre fait foi est celui
 * que le directeur règle ensuite à la main (classes.sort_order).
 */
class PedagogicalOrder
{
    // Du plus petit au plus grand. Motifs testés sur le libellé en minuscules.
    private const LEVELS = [
        '/\b(tps|toute petite)/u',
        '/\b(ps|petite section|maternelle ?1|mat ?1)\b/u',
        '/\b(ms|moyenne section|maternelle ?2|mat ?2)\b/u',
        '/\b(gs|grande section|maternelle ?3|mat ?3)\b/u',
        '/\bci\b/u',
        '/\bcp\b/u',
        '/\bce ?1\b/u',
        '/\bce ?2\b/u',
        '/\bcm ?1\b/u',
        '/\bcm ?2\b/u',
        '/\b6 ?(e|è|ème|eme)\b/u',
        '/\b5 ?(e|è|ème|eme)\b/u',
        '/\b4 ?(e|è|ème|eme)\b/u',
        '/\b3 ?(e|è|ème|eme)\b/u',
        '/\b(2 ?(nde|nd|de)|seconde)\b/u',
        '/\b(1 ?(ère|ere|re)|premi[eè]re)\b/u',
        '/\b(tle|terminale)\b/u',
    ];

    public static function rank(string $label): int
    {
        $normalized = mb_strtolower(trim($label));

        foreach (self::LEVELS as $index => $pattern) {
            if (preg_match($pattern, $normalized)) {
                return $index;
            }
        }

        return count(self::LEVELS); // inconnu : en fin, départagé par le libellé
    }

    /**
     * @param  iterable<object{id:int,label:string,cycle_order:int}>  $classes
     * @return int[] identifiants dans l'ordre proposé
     */
    public static function sort(iterable $classes): array
    {
        return collect($classes)
            ->sort(fn ($a, $b) => [$a->cycle_order, self::rank($a->label), mb_strtolower($a->label)]
                <=> [$b->cycle_order, self::rank($b->label), mb_strtolower($b->label)])
            ->pluck('id')
            ->all();
    }
}
