<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->pages() as $page) {
            $existingCreatedAt = DB::table('pages')
                ->where('slug', $page['slug'])
                ->value('created_at');

            DB::table('pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'updated_at' => $now,
                    'created_at' => $existingCreatedAt ?? $now,
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('pages')
            ->whereIn('slug', array_column($this->pages(), 'slug'))
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pages(): array
    {
        return [
            [
                'title' => 'Digitaal onderhoudsboekje als centrale voertuiggeschiedenis',
                'slug' => 'digitaal-onderhoudsboekje',
                'content' => <<<'HTML'
<p>Bewaar onderhoud, documenten, kilometerstanden en bewijs centraal op één plek voor bezit, verkoop en overdracht.</p>
<h2>Wat is een digitaal onderhoudsboekje vandaag eigenlijk?</h2>
<p>Niet alleen een plek voor beurten en bonnetjes, maar de centrale plek voor de complete voertuiggeschiedenis van één voertuig. Daarmee vervang je niet alleen een papieren boekje, maar bouw je een digitale historie op die bruikbaar blijft voor dagelijks overzicht, sleutelwerk, waardebehoud, verkoop, taxatie en overdracht.</p>
<h2>Voor wie is een centrale digitale historie waardevol?</h2>
<p>GarageBook is gemaakt voor eigenaren, kopers, garages, taxateurs en liefhebbers die meer willen dan losse notities. Onderhoud, foto's, documenten, kilometerstanden en bewijsstukken horen bij hetzelfde verhaal.</p>
<ul>
<li>Overdraagbare historie voor auto's, motoren en klassiekers</li>
<li>Facturen, foto's, bewijsstukken en kilometerstanden samen</li>
<li>Meer transparantie, waardebehoud en vertrouwen bij verkoop</li>
</ul>
<h2>Meer dan een dealerboekje of een spreadsheet</h2>
<p>Dealerhistorie is nuttig, maar vertelt zelden het hele verhaal van een voertuig. Eigen onderhoud, upgrades, losse reparaties en specialistisch werk vallen daar vaak buiten. Excel of notities werken als startpunt, maar raken snel versnipperd zodra je foto's, documenten en meerdere voertuigen wilt beheren.</p>
<h2>Bouw aan historie die je later kunt delen</h2>
<p>Een digitale historie krijgt extra waarde zodra je die niet alleen kunt bewaren, maar ook gecontroleerd kunt laten zien aan een koper, garage of specialist. Juist daarom past de taal van voertuiggeschiedenis beter dan de taal van een losse tool of tijdelijke app.</p>
<p>Lees ook verder op <a href="/motor-onderhoud-app">motor onderhoud app</a>, <a href="/motor-onderhoud-bijhouden">motor onderhoud bijhouden</a> en <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a>.</p>
HTML,
                'meta_title' => 'Digitaal onderhoudsboekje als centrale voertuiggeschiedenis',
                'meta_description' => 'Gebruik GarageBook als centrale voertuiggeschiedenis voor onderhoud, documenten, kilometerstanden, bewijs en overdraagbare digitale historie.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
            ],
            [
                'title' => 'Motor onderhoud app voor je onderhoudshistorie',
                'slug' => 'motor-onderhoud-app',
                'content' => <<<'HTML'
<p>Start gratis met GarageBook en houd onderhoud, reparaties, kilometerstanden, kosten, foto's en facturen van je motor bij zonder Excel-chaos.</p>
<h2>Waarom een motor onderhoud app?</h2>
<p>Een goede app brengt onderhoudsbeurten, bewijs, onderdelen, documenten en verkoopwaarde per motor samen. Daardoor voelt het minder als een extra feature en meer als een vaste plek voor alles wat je aan je motor doet.</p>
<h2>Wat kun je bijhouden?</h2>
<ul>
<li>Onderhoudsbeurten en reparaties</li>
<li>Upgrades en accessoires</li>
<li>Kilometerstanden en kosten</li>
<li>Foto's, facturen en documenten</li>
</ul>
<h2>Waarom niet gewoon Excel?</h2>
<p>Excel is flexibel, maar op mobiel wordt het snel rommelig. Een app werkt prettiger zodra je ook foto's, facturen, meerdere voertuigen en complete historie wilt bewaren.</p>
<h2>Voor wie is GarageBook geschikt?</h2>
<p>Voor zelf-sleutelaars, tourrijders, circuitrijders, klassiekerbezitters en iedereen die later goed wil verkopen met een sterke onderhoudshistorie.</p>
<p>Bekijk ook <a href="/motor-onderhoud-bijhouden">motor onderhoud bijhouden</a>, <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a> en <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>.</p>
HTML,
                'meta_title' => 'Motor onderhoud app – start gratis | GarageBook',
                'meta_description' => 'Start gratis met GarageBook en houd motoronderhoud, facturen en kilometerstanden bij. Bewaar bewijs dat de waarde van je motor ondersteunt.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
            ],
            [
                'title' => 'Motor onderhoud bijhouden: complete historie opbouwen',
                'slug' => 'motor-onderhoud-bijhouden',
                'content' => <<<'HTML'
<p>Houd onderhoud, reparaties, kosten, kilometerstanden en bewijs van je motor overzichtelijk bij. Zo bouw je een complete motorhistorie op die ook bij verkoop sterker voelt.</p>
<h2>Wat moet je minimaal bijhouden?</h2>
<ul>
<li>Datum en kilometerstand</li>
<li>Werkzaamheden en gebruikte onderdelen</li>
<li>Vloeistoffen en kosten</li>
<li>Foto's, facturen en opmerkingen voor later</li>
</ul>
<h2>De 5 manieren waarop motorrijders onderhoud bijhouden</h2>
<p>Bijna iedereen begint simpel: geheugen, papier, Excel, notities of foto's in de telefoon. Het echte probleem is versnippering. Een centrale onderhoudshistorie maakt de informatie bruikbaar.</p>
<h2>Waarom onderhoud bijhouden geld waard kan zijn</h2>
<p>Een complete historie geeft vertrouwen, verkleint discussie en helpt om de staat van onderhoud beter te onderbouwen. Zeker als je zelf veel hebt gedaan, maakt bewijs het verhaal concreter.</p>
<p>Lees verder op <a href="/motor-onderhoud-app">motor onderhoud app</a>, <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a> en <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>.</p>
HTML,
                'meta_title' => 'Motor onderhoud bijhouden – complete historie | GarageBook',
                'meta_description' => 'Houd onderhoud, reparaties, kosten, kilometerstanden en bewijs van je motor overzichtelijk bij. Bekijk hoe je een complete motorhistorie opbouwt.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
            ],
            [
                'title' => 'Onderhoudsboekje motor digitaal bijhouden',
                'slug' => 'onderhoudsboekje-motor',
                'content' => <<<'HTML'
<p>Vervang losse stempels, bonnetjes en notities door een digitaal onderhoudsboekje voor je motor. Bewaar onderhoud, kosten, kilometerstanden en bewijs op één plek.</p>
<h2>Waarom een digitaal onderhoudsboekje beter werkt</h2>
<p>Niet alleen de stempel telt, maar het hele verhaal eromheen: wat is gedaan, bij welke kilometerstand, met welke onderdelen en met welk bewijs. Een papieren boekje wordt daarvoor al snel te klein en te los.</p>
<h2>Wat hoort erin?</h2>
<ul>
<li>Onderhoudsmomenten en kilometerstanden</li>
<li>Uitgevoerde werkzaamheden en gebruikte onderdelen</li>
<li>Kosten, documenten, foto's en notities</li>
</ul>
<h2>Zelf onderhoud gedaan?</h2>
<p>Door datum, kilometerstand, onderdelen, foto's en facturen van materialen vast te leggen kun je ook zelf uitgevoerd onderhoud goed onderbouwen.</p>
<p>Lees ook <a href="/motor-onderhoud-app">motor onderhoud app</a>, <a href="/motor-onderhoud-bijhouden">motor onderhoud bijhouden</a> en <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>.</p>
HTML,
                'meta_title' => 'Onderhoudsboekje motor digitaal bijhouden | GarageBook',
                'meta_description' => 'Vervang losse stempels, bonnetjes en notities door een digitaal onderhoudsboekje voor je motor. Bewaar onderhoud, kosten, km-standen en bewijs.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
            ],
        ];
    }
};
