<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages légales. Le pied de page y renvoyait déjà : sans elles, les liens
 * « Conditions générales » et « Politique de confidentialité » tombaient en 404.
 *
 * Le contenu est un brouillon de travail, modifiable dans le backoffice. Les
 * mentions entre crochets sont à compléter par l'entreprise — un juriste doit
 * relire avant mise en ligne.
 */
class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            // firstOrCreate : un contenu déjà rédigé dans l'admin n'est pas écrasé
            Page::firstOrCreate(['slug' => $page['slug']], $page);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function pages(): array
    {
        return [
            [
                'slug'             => 'conditions-generales',
                'title'            => 'Conditions générales de vente',
                'meta_title'       => 'Conditions générales de vente — RT Water Solution',
                'meta_description' => "Conditions générales de vente applicables aux commandes passées sur le site de RT Water Solution.",
                'is_published'     => true,
                'sort_order'       => 90,
                'content'          => $this->terms(),
            ],
            [
                'slug'             => 'politique-de-confidentialite',
                'title'            => 'Politique de confidentialité',
                'meta_title'       => 'Politique de confidentialité — RT Water Solution',
                'meta_description' => "Données collectées, finalités, durée de conservation et vos droits sur le site de RT Water Solution.",
                'is_published'     => true,
                'sort_order'       => 91,
                'content'          => $this->privacy(),
            ],
            [
                'slug'             => 'mentions-legales',
                'title'            => 'Mentions légales',
                'meta_title'       => 'Mentions légales — RT Water Solution',
                'meta_description' => "Éditeur du site, hébergement et contact.",
                'is_published'     => true,
                'sort_order'       => 92,
                'content'          => $this->legal(),
            ],
        ];
    }

    private function terms(): string
    {
        return <<<'HTML'
<p><em>Dernière mise à jour : à compléter. Document à faire relire par un juriste avant mise en ligne.</em></p>

<h2>1. Objet</h2>
<p>Les présentes conditions régissent les ventes de produits et de prestations réalisées par
RT Water Solution, ci-après « le Vendeur », auprès de tout acheteur, ci-après « le Client »,
via le site rtwater.cm. Toute commande vaut acceptation sans réserve des présentes conditions.</p>

<h2>2. Produits et prix</h2>
<p>Les produits sont décrits et présentés avec la plus grande exactitude possible. Les prix sont
indiqués en francs CFA (XAF), toutes taxes comprises, hors frais de livraison. Le Vendeur se
réserve le droit de modifier ses prix à tout moment ; le prix applicable est celui affiché au
moment de la validation de la commande.</p>

<h2>3. Commande</h2>
<p>Le Client sélectionne ses articles, renseigne son adresse de livraison et valide sa commande.
Un récapitulatif lui est présenté avant paiement. La commande est ferme dès réception du
paiement ou, en cas de paiement à la livraison, dès confirmation par le Vendeur.</p>

<h2>4. Paiement</h2>
<p>Le paiement s'effectue par Mobile Money (Orange Money, MTN MoMo) ou en espèces à la livraison.
Les transactions Mobile Money sont traitées par notre prestataire de paiement ; le Vendeur n'a
jamais accès aux identifiants du Client.</p>

<h2>5. Livraison</h2>
<p>Les délais et frais de livraison dépendent de la zone sélectionnée lors de la commande et sont
affichés avant paiement. Les délais annoncés sont indicatifs. En cas de retard, le Client est
informé dans les meilleurs délais.</p>

<h2>6. Droit de rétractation et retours</h2>
<p>Le Client dispose de [à compléter] jours à compter de la réception pour signaler un produit
non conforme ou défectueux. Le produit doit être retourné dans son emballage d'origine, non
utilisé. Les frais de retour sont [à compléter : à la charge du Client / du Vendeur]. Pour des
raisons d'hygiène, les consommables descellés ne peuvent être repris.</p>

<h2>7. Garantie</h2>
<p>Les équipements vendus bénéficient de la garantie légale de conformité et, le cas échéant, de
la garantie constructeur dont la durée est précisée sur la fiche produit. La garantie ne couvre
pas l'usure normale, une installation non conforme ni un défaut d'entretien.</p>

<h2>8. Responsabilité</h2>
<p>La responsabilité du Vendeur ne saurait être engagée pour les dommages résultant d'une
utilisation non conforme des produits ou d'une installation réalisée par un tiers.</p>

<h2>9. Données personnelles</h2>
<p>Le traitement des données du Client est décrit dans notre
<a href="/pages/politique-de-confidentialite">politique de confidentialité</a>.</p>

<h2>10. Litiges</h2>
<p>Les présentes conditions sont soumises au droit camerounais. En cas de litige, les parties
rechercheront une solution amiable avant toute action judiciaire. À défaut, les tribunaux de
[ville à compléter] seront seuls compétents.</p>

<h2>11. Contact</h2>
<p>Pour toute question relative à une commande : [e-mail à compléter] — [téléphone à compléter].</p>
HTML;
    }

    private function privacy(): string
    {
        return <<<'HTML'
<p><em>Dernière mise à jour : à compléter. Document à faire relire par un juriste avant mise en ligne.</em></p>

<h2>1. Qui traite vos données</h2>
<p>RT Water Solution, [forme juridique et adresse à compléter], est responsable du traitement des
données collectées sur ce site. Contact : [e-mail à compléter].</p>

<h2>2. Données collectées et pourquoi</h2>
<table>
  <thead><tr><th>Données</th><th>Finalité</th><th>Base légale</th></tr></thead>
  <tbody>
    <tr><td>Nom, téléphone, adresse</td><td>Traiter et livrer votre commande</td><td>Exécution du contrat</td></tr>
    <tr><td>Adresse e-mail</td><td>Confirmation de commande, suivi, assistance</td><td>Exécution du contrat</td></tr>
    <tr><td>Adresse e-mail (lettre d'information)</td><td>Vous envoyer nos actualités</td><td>Votre consentement</td></tr>
    <tr><td>Historique de commandes</td><td>Service après-vente, comptabilité</td><td>Obligation légale</td></tr>
    <tr><td>Mesure d'audience anonymisée</td><td>Comprendre l'usage du site</td><td>Votre consentement</td></tr>
  </tbody>
</table>

<h2>3. Paiement</h2>
<p>Les paiements Mobile Money sont traités par notre prestataire. Nous ne conservons aucun code
secret ni identifiant de paiement : seuls la référence de transaction et son statut sont
enregistrés, afin de rattacher le règlement à votre commande.</p>

<h2>4. Combien de temps nous les gardons</h2>
<p>Les données de commande sont conservées [durée à compléter, typiquement 10 ans] pour répondre
aux obligations comptables. Les inscriptions à la lettre d'information sont conservées jusqu'à
votre désinscription. Les comptes inactifs depuis [durée à compléter] sont supprimés.</p>

<h2>5. Qui y a accès</h2>
<p>Vos données sont accessibles à notre équipe dans la limite de ses missions, et à nos
sous-traitants techniques : hébergeur, prestataire de paiement, service d'envoi d'e-mails.
Elles ne sont jamais vendues ni louées.</p>

<h2>6. Cookies</h2>
<p>Le site utilise des cookies techniques, nécessaires à son fonctionnement (panier, session), et,
avec votre accord, des cookies de mesure d'audience. Vous pouvez modifier votre choix à tout
moment depuis le bandeau de consentement ou les réglages de votre navigateur.</p>

<h2>7. Vos droits</h2>
<p>Vous pouvez demander l'accès, la rectification, l'effacement ou la portabilité de vos données,
ainsi que vous opposer à leur traitement. Écrivez à [e-mail à compléter] ; nous répondons sous
[délai à compléter]. Pour la lettre d'information, chaque envoi comporte un lien de
désinscription immédiat.</p>

<h2>8. Sécurité</h2>
<p>Les échanges avec le site sont chiffrés (HTTPS). Les mots de passe sont stockés sous forme
hachée et ne sont connus de personne, pas même de notre équipe.</p>
HTML;
    }

    private function legal(): string
    {
        return <<<'HTML'
<p><em>À compléter avec les informations officielles de l'entreprise.</em></p>

<h2>Éditeur du site</h2>
<p>
  RT Water Solution<br>
  [Forme juridique et capital social]<br>
  [Adresse du siège social]<br>
  Numéro d'identifiant unique (NIU) : [à compléter]<br>
  Registre du commerce : [à compléter]<br>
  Téléphone : [à compléter] — E-mail : [à compléter]
</p>

<h2>Responsable de la publication</h2>
<p>[Nom et qualité à compléter]</p>

<h2>Hébergement</h2>
<p>[Nom de l'hébergeur, adresse et contact à compléter]</p>

<h2>Propriété intellectuelle</h2>
<p>L'ensemble des contenus de ce site — textes, visuels, logo, structure — est protégé. Toute
reproduction, même partielle, sans autorisation écrite préalable est interdite.</p>

<h2>Signaler un contenu</h2>
<p>Pour signaler une erreur ou un contenu litigieux : [e-mail à compléter].</p>
HTML;
    }
}
