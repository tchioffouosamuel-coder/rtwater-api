<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            // 1. Accueil
            [
                'title'        => 'Accueil',
                'slug'         => 'accueil',
                'is_published' => true,
                'sort_order'   => 1,
                'content'      => json_encode([
                    'hero' => [
                        'badge'               => "Leader en traitement d'eau",
                        'title'               => "Solutions complètes pour vos",
                        'titleHighlight'      => "besoins en eau",
                        'description'         => "Purification, forage et services de piscine professionnels à Yaoundé. Votre partenaire de confiance pour une eau pure et saine.",
                        'backgroundImage'     => "https://6a36433705d679a122fa04b3.imgix.net/my-images/beautiful-african-women-having-fun-while-fetching-water.jpg?auto=format&fit=fill&w=384",
                        'servicesButtonLabel' => "Nos services",
                        'contactButtonLabel'  => "Contactez-nous",
                    ],
                    'services' => [
                        'sectionSubtitle'   => "Ce que nous offrons",
                        'sectionTitle'      => "Nos Services",
                        'sectionDescription' => "Des solutions complètes pour tous vos besoins en eau, de la purification à l'installation. Excellence et innovation à chaque étape.",
                        'backgroundImage'   => "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1920&q=80",
                        'items' => [
                            ['icon' => 'droplet', 'title' => "Purification d'eau",  'description' => "Systèmes de purification avancés pour une eau potable de qualité supérieure, adaptés à tous vos besoins.", 'gradient' => 'from-blue-500 to-cyan-500'],
                            ['icon' => 'zap',     'title' => "Vente de pompes",      'description' => "Large gamme de pompes à eau performantes et durables pour tous types d'installations résidentielles et industrielles.", 'gradient' => 'from-[#00A8E8] to-[#0095D9]'],
                            ['icon' => 'beaker',  'title' => "Désinfectants",        'description' => "Produits de désinfection professionnels pour garantir la salubrité et la sécurité de votre eau.", 'gradient' => 'from-green-500 to-emerald-500'],
                            ['icon' => 'drill',   'title' => "Forage",               'description' => "Services de forage professionnel pour l'accès à des sources d'eau souterraines fiables et durables.", 'gradient' => 'from-[#0077BE] to-[#00A8E8]'],
                            ['icon' => 'waves',   'title' => "Piscines",             'description' => "Installation, entretien et traitement complet de piscines pour une eau cristalline toute l'année.", 'gradient' => 'from-cyan-500 to-blue-500'],
                            ['icon' => 'wrench',  'title' => "Maintenance",          'description' => "Services de maintenance préventive et corrective pour tous vos équipements de traitement d'eau.", 'gradient' => 'from-[#0095D9] to-[#0077BE]'],
                        ],
                    ],
                    'trust' => [
                        'sectionSubtitle'      => "Notre impact",
                        'sectionTitle'         => "Pourquoi nous faire",
                        'sectionTitleHighlight' => "confiance ?",
                        'sectionDescription'   => "Des années d'expertise au service de votre eau. Des chiffres qui parlent d'eux-mêmes.",
                        'stats' => [
                            ['value' => '15',   'suffix' => '+', 'label' => "Années d'expérience", 'description' => "Dans le traitement de l'eau"],
                            ['value' => '500',  'suffix' => '+', 'label' => "Clients satisfaits",  'description' => "À travers le Cameroun"],
                            ['value' => '1000', 'suffix' => '+', 'label' => "Projets réalisés",     'description' => "Avec succès"],
                        ],
                    ],
                    'cta' => [
                        'badge'               => "Prêt à commencer ?",
                        'title'               => "Besoin d'une solution",
                        'titleHighlight'      => "en eau ?",
                        'description'         => "Contactez-nous dès aujourd'hui pour un devis gratuit et personnalisé. Notre équipe d'experts est prête à répondre à tous vos besoins.",
                        'contactButtonLabel'  => "Contactez-nous",
                        'servicesButtonLabel' => "Découvrir nos services",
                        'trustIndicators'     => ["Disponible 24/7", "Devis gratuit", "Installation rapide"],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],

            // 2. À propos
            [
                'title'        => 'À propos',
                'slug'         => 'a-propos',
                'is_published' => true,
                'sort_order'   => 2,
                'content'      => json_encode([
                    'heroBadge'          => "Notre histoire",
                    'heroTitle'          => "À propos de",
                    'heroTitleHighlight' => "RT-Water",
                    'heroDescription'    => "Depuis 2008, nous sommes le partenaire de confiance pour toutes vos solutions en eau au Cameroun.",
                    'missionBadge'       => "Notre mission",
                    'missionTitle'       => "Fournir de l'eau pure pour tous",
                    'missionDescription1' => "Notre mission est de garantir l'accès à une eau propre, saine et fiable pour tous nos clients. Nous croyons que l'eau de qualité est un droit fondamental et nous travaillons chaque jour pour le rendre accessible.",
                    'missionDescription2' => "Avec une équipe de professionnels hautement qualifiés et des équipements de pointe, nous offrons des solutions complètes adaptées à chaque besoin, qu'il s'agisse de purification, de forage, ou de maintenance.",
                    'stats' => [
                        ['value' => '15+',   'label' => "Années d'expérience"],
                        ['value' => '1000+', 'label' => "Projets réalisés"],
                        ['value' => '500+',  'label' => "Clients satisfaits"],
                        ['value' => '20+',   'label' => "Zones couvertes"],
                    ],
                    'valuesBadge'  => "Nos valeurs",
                    'valuesTitle'  => "Ce qui nous définit",
                    'values' => [
                        ['icon' => 'droplet', 'title' => "Qualité supérieure", 'description' => "Nous utilisons uniquement des équipements et produits de qualité certifiée pour garantir l'excellence de nos services.", 'gradient' => 'from-blue-500 to-cyan-500'],
                        ['icon' => 'zap',     'title' => "Innovation",         'description' => "Nous adoptons les technologies les plus récentes pour offrir des solutions modernes et efficaces.", 'gradient' => 'from-[#00A8E8] to-[#0095D9]'],
                        ['icon' => 'award',   'title' => "Expertise",          'description' => "Notre équipe de professionnels certifiés possède plus de 15 ans d'expérience dans le domaine.", 'gradient' => 'from-[#0077BE] to-[#00A8E8]'],
                        ['icon' => 'users',   'title' => "Service client",     'description' => "Nous sommes disponibles 24/7 pour répondre à vos besoins et assurer votre satisfaction.", 'gradient' => 'from-green-500 to-emerald-500'],
                    ],
                    'timelineBadge'  => "Notre parcours",
                    'timelineTitle'  => "15 ans d'excellence",
                    'timeline' => [
                        ['year' => '2008', 'event' => "Création de RT Water Solution à Yaoundé"],
                        ['year' => '2012', 'event' => "Expansion des services de forage"],
                        ['year' => '2015', 'event' => "Lancement des services de piscines"],
                        ['year' => '2018', 'event' => "500+ clients satisfaits"],
                        ['year' => '2020', 'event' => "Certification ISO obtenue"],
                        ['year' => '2024', 'event' => "Leader du marché camerounais"],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],

            // 3. Contact
            [
                'title'        => 'Contact',
                'slug'         => 'contact',
                'is_published' => true,
                'sort_order'   => 3,
                'content'      => json_encode([
                    'address' => "Yaoundé, Cameroun",
                    'phone'   => "+237 6XX XXX XXX",
                    'email'   => "info@rt-water.cm",
                    'hours'   => "Lun - Ven : 08:00 - 17:00",
                ], JSON_UNESCAPED_UNICODE),
            ],

            // 4. Galerie
            [
                'title'        => 'Galerie',
                'slug'         => 'galerie',
                'is_published' => true,
                'sort_order'   => 4,
                'content'      => json_encode([
                    'badge'          => "Nos réalisations",
                    'title'          => "Galerie de",
                    'titleHighlight' => "projets",
                    'description'    => "Découvrez nos réalisations et notre expertise à travers nos projets en Afrique",
                    'images' => [
                        ['url' => 'https://images.unsplash.com/photo-1738956312126-01ba60788071?w=1080', 'title' => "Forage professionnel",          'category' => 'drilling'],
                        ['url' => 'https://images.unsplash.com/photo-1708287523092-0543d6803857?w=1080', 'title' => "Station de purification",       'category' => 'purification'],
                        ['url' => 'https://images.unsplash.com/photo-1758530273222-440d6a8b0eea?w=1080', 'title' => "Entretien de piscines",         'category' => 'pools'],
                        ['url' => 'https://images.unsplash.com/photo-1712640379137-6d2532f887a7?w=1080', 'title' => "Installation de pompes",        'category' => 'pumps'],
                        ['url' => 'https://images.unsplash.com/photo-1680200023508-5289ae3de157?w=1080', 'title' => "Eau propre pour la communauté", 'category' => 'projects'],
                        ['url' => 'https://images.unsplash.com/photo-1613929906260-c9377d135547?w=1080', 'title' => "Tuyauterie industrielle",       'category' => 'installation'],
                        ['url' => 'https://images.unsplash.com/photo-1764344815153-3a356c392561?w=1080', 'title' => "Réservoirs de stockage",        'category' => 'installation'],
                        ['url' => 'https://images.unsplash.com/photo-1635221798248-8a3452ad07cd?w=1080', 'title' => "Travaux professionnels",        'category' => 'maintenance'],
                        ['url' => 'https://images.unsplash.com/photo-1570615541379-e6b7ab6d4eb9?w=1080', 'title' => "Traitement d'eau avancé",       'category' => 'purification'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],

            // 5. En-tête (site-header)
            [
                'title'        => 'En-tête',
                'slug'         => 'site-header',
                'is_published' => true,
                'sort_order'   => 5,
                'content'      => json_encode([
                    'logoUrl'        => "/logo.svg",
                    'companyName'    => "RT Water",
                    'companyTagline' => "Solution",
                    'navLinks' => [
                        ['label' => "Accueil",      'path' => "/"],
                        ['label' => "Galerie",      'path' => "/gallery"],
                        ['label' => "À propos",     'path' => "/about"],
                        ['label' => "Devis gratuit",'path' => "/devis"],
                        ['label' => "Boutique",     'path' => "/shop"],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],

            // 6. Pied de page (site-footer)
            [
                'title'        => 'Pied de page',
                'slug'         => 'site-footer',
                'is_published' => true,
                'sort_order'   => 6,
                'content'      => json_encode([
                    'logoUrl'        => "/logo.svg",
                    'companyName'    => "RT Water",
                    'companyTagline' => "Solution",
                    'tagline'        => "Votre partenaire de confiance pour des solutions complètes en eau à Yaoundé, Cameroun. Excellence et innovation depuis plus de 15 ans.",
                    'serviceLinks'   => [
                        "Purification d'eau",
                        "Vente de pompes",
                        "Désinfectants",
                        "Forage",
                        "Piscines",
                        "Maintenance",
                    ],
                    'address'     => "Yaoundé, Cameroun",
                    'phone'       => "+237 6XX XXX XXX",
                    'email'       => "info@rt-water.cm",
                    'socialLinks' => [
                        ['platform' => 'facebook',  'url' => '#'],
                        ['platform' => 'twitter',   'url' => '#'],
                        ['platform' => 'linkedin',  'url' => '#'],
                        ['platform' => 'instagram', 'url' => '#'],
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }
}
