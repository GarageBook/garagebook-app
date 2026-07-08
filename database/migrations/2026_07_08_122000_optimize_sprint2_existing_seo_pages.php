<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->pages() as $page) {
            $faqItems = $page['structured_data'] ?? null;
            unset($page['structured_data']);

            DB::table('pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'structured_data' => $faqItems ? json_encode($faqItems, JSON_UNESCAPED_UNICODE) : null,
                    'updated_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        // Content updates; no rollback defined — prior content lives in git history
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'digitaal-onderhoudsboekje',
                'title' => 'Digitaal onderhoudsboekje als centrale voertuiggeschiedenis',
                'content' => <<<'HTML'
<p>Een digitaal onderhoudsboekje is meer dan een app of een spreadsheet. Het is de centrale plek voor de complete voertuiggeschiedenis van één voertuig: onderhoud, documenten, kilometerstanden, foto's, facturen en bewijs. Alles op één plek, altijd beschikbaar, overdraagbaar bij verkoop.</p>

<h2>Wat is een digitaal onderhoudsboekje vandaag eigenlijk?</h2>
<p>Niet alleen een plek voor beurten en bonnetjes, maar de centrale plek voor de complete voertuiggeschiedenis van één voertuig. Daarmee vervang je niet alleen een papieren boekje, maar bouw je een digitale historie op die bruikbaar blijft voor dagelijks overzicht, sleutelwerk, waardebehoud, verkoop, taxatie en overdracht.</p>

<h2>Voor wie is een digitaal onderhoudsboekje waardevol?</h2>
<p>GarageBook is gemaakt voor eigenaren, kopers, taxateurs en liefhebbers die meer willen dan losse notities. Onderhoud, foto's, documenten, kilometerstanden en bewijsstukken horen bij hetzelfde verhaal.</p>
<ul>
<li>Overdraagbare historie voor auto's, motoren en klassiekers</li>
<li>Facturen, foto's, bewijsstukken en kilometerstanden samen</li>
<li>Meer transparantie, waardebehoud en vertrouwen bij verkoop</li>
</ul>

<h2>Meer dan een dealerboekje of een spreadsheet</h2>
<p>Dealerhistorie is nuttig, maar vertelt zelden het hele verhaal van een voertuig. Eigen onderhoud, upgrades, losse reparaties en specialistisch werk vallen er buiten. Excel of notities werken als startpunt, maar raken snel versnipperd zodra je ook foto's, facturen, meerdere voertuigen en complete historie wilt bewaren.</p>

<h2>Wat leg je vast in een digitaal onderhoudsboekje?</h2>
<ul>
<li>Periodieke beurten: olie, filters, vloeistoffen</li>
<li>Grotere werkzaamheden: remmen, distributie, koppeling, ketting</li>
<li>APK-keuringen en uitslagen</li>
<li>Banden: merk, maat, datum</li>
<li>Upgrades, accessoires en aanpassingen</li>
<li>Reparaties en schade-incidenten</li>
<li>Foto's van de toestand voor en na werkzaamheden</li>
<li>Originele facturen en bonnetjes</li>
</ul>

<h2>Bouw aan een historie die je later kunt delen</h2>
<p>Een digitale historie krijgt extra waarde zodra je die niet alleen kunt bewaren, maar ook gecontroleerd kunt laten zien aan een koper, garage of specialist. Juist daarom past de taal van voertuiggeschiedenis beter dan de taal van een losse tool of tijdelijke app.</p>

<h2>Digitaal onderhoudsboekje bij verkoop</h2>
<p>Bij de verkoop van je auto of motor is een aantoonbare onderhoudshistorie een van de sterkste troeven. Met een digitale history deel je de complete documentatie met een potentiële koper – inclusief foto's, facturen en aantekeningen. Dat geeft vertrouwen en kan de verkoopprijs positief beïnvloeden.</p>

<h2>Zelf onderhoud doen? Dat maakt je history sterker</h2>
<p>Eigenaren die zelf sleutelen, hebben geen dealer-stempel maar weten precies wat ze hebben gedaan. Leg je eigen werkzaamheden vast met datum, kilometerstand, gebruikte onderdelen, foto's en bonnetjes. Zo is zelf uitgevoerd onderhoud even aantoonbaar als een officiële beurt.</p>

<p>Lees ook verder op <a href="/digitaal-onderhoudsboekje-auto">digitaal onderhoudsboekje auto</a>, <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a>, <a href="/onderhoudshistorie-auto">onderhoudshistorie auto</a> en <a href="/onderhoudsboekje-kwijt">onderhoudsboekje kwijt</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Digitaal onderhoudsboekje – centrale voertuiggeschiedenis | GarageBook',
                'meta_description' => "Gebruik GarageBook als digitaal onderhoudsboekje voor auto's en motoren. Bewaar onderhoud, facturen, foto's en kilometerstanden op één overdraagbare plek.",
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is een digitaal onderhoudsboekje?',
                        'answer' => 'Een digitaal onderhoudsboekje is een online omgeving waar je de complete onderhoudshistorie van je voertuig bijhoudt. In tegenstelling tot een papieren boekje bevat een digitale versie ook foto\'s, facturen, aantekeningen en meerdere voertuigen, en is het altijd beschikbaar en nooit kwijt te raken.',
                    ],
                    [
                        'question' => 'Wat is het beste digitale onderhoudsboekje voor auto en motor?',
                        'answer' => 'GarageBook is een digitaal onderhoudsboekje speciaal gebouwd voor auto\'s, motoren en klassiekers. Je legt beurten vast met datum, kilometerstand, beschrijving, foto\'s en facturen. Het is gratis te starten en overdraagbaar bij verkoop.',
                    ],
                    [
                        'question' => 'Is een digitaal onderhoudsboekje geldig bij verkoop?',
                        'answer' => 'Ja. Er is geen wettelijke verplichting voor een papieren boekje. Een digitale history met facturen en foto\'s is inhoudelijk minstens zo sterk en bevat in de meeste gevallen meer details dan een papieren stempel.',
                    ],
                    [
                        'question' => 'Hoe begin ik met een digitaal onderhoudsboekje?',
                        'answer' => 'Registreer gratis bij GarageBook, voeg je voertuig toe en begin met het invoeren van bestaande onderhoudsgegevens. Daarna log je nieuwe beurten direct na uitvoering en upload je facturen en foto\'s als bewijs.',
                    ],
                    [
                        'question' => 'Kan ik meerdere voertuigen bijhouden in één digitaal onderhoudsboekje?',
                        'answer' => 'Ja. Via GarageBook beheer je meerdere voertuigen, auto\'s, motoren en klassiekers, in één overzicht. Ieder voertuig heeft een eigen tijdlijn.',
                    ],
                ],
            ],
            [
                'slug' => 'motor-onderhoud-app',
                'title' => 'Motor onderhoud app voor je onderhoudshistorie',
                'content' => <<<'HTML'
<p>Met een goede motor onderhoud app houd je onderhoud, reparaties, kilometerstanden, kosten, foto's en facturen van je motor bij in één centrale tijdlijn. GarageBook is gratis te starten en gebouwd voor motorrijders die hun rijder serieus nemen.</p>

<h2>Waarom een motor onderhoud app?</h2>
<p>Een goede app brengt onderhoudsbeurten, bewijs, onderdelen, documenten en verkoopwaarde per motor samen. Daardoor voelt het minder als een extra klus en meer als een vaste plek voor alles wat je aan je motor doet.</p>

<h2>Wat kun je bijhouden met een motor onderhoud app?</h2>
<ul>
<li>Onderhoudsbeurten en reparaties met datum en kilometerstand</li>
<li>Upgrades, accessoires en aanpassingen</li>
<li>Kilometerstanden en kosten</li>
<li>Foto's van de motor en werkzaamheden</li>
<li>Facturen van de werkplaats</li>
<li>Bonnetjes van eigen onderdelen en materialen</li>
<li>APK-keuringen</li>
<li>Aandachtspunten en reminders voor volgend onderhoud</li>
</ul>

<h2>Waarom niet gewoon Excel of notities?</h2>
<p>Excel is flexibel, maar op mobiel wordt het snel rommelig. Een app werkt prettiger zodra je ook foto's, facturen, meerdere voertuigen en complete historie wilt bewaren. Notities in je telefoon raken versnipperd en zijn niet overdraagbaar bij verkoop van je motor.</p>

<h2>Voor wie is GarageBook als motor onderhoud app geschikt?</h2>
<p>Voor zelf-sleutelaars die hun eigen werk willen aantonen, tourrijders die alles willen documenteren, circuitrijders met hoge onderhoudsfrequentie, klassiekerbezitters met waardevolle documentatiegeschiedenis en iedereen die later goed wil verkopen met een sterke onderhoudshistorie.</p>

<h2>Zelf onderhoud doen en toch aantoonbaar bijhouden</h2>
<p>Wie zijn motor zelf onderhoudt, mist het officiële dealerstempel. Met GarageBook leg je eigen werkzaamheden vast met datum, kilometerstand, gebruikte materialen, foto's en bonnetjes. Dat is overtuigender dan een leeg boekje bij verkoop.</p>

<h2>Motor onderhoud app en verkoopwaarde</h2>
<p>Een motor met complete, aantoonbare onderhoudshistorie is meer waard. Kopers waarderen transparantie en zekerheid. Wie zijn motor verkoopt met een GarageBook-tijdlijn, staat sterker dan een verkoper die alleen mondeling informatie kan overleggen.</p>

<p>Bekijk ook <a href="/motor-onderhoud-bijhouden">motor onderhoud bijhouden</a>, <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a>, <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a> en <a href="/onderhoudshistorie-motor">onderhoudshistorie motor</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Motor onderhoud app – bijhouden, bewaren, overdragen | GarageBook',
                'meta_description' => 'Houd motoronderhoud, facturen, kilometerstanden en foto\'s bij met GarageBook. De motor onderhoud app voor zelfsleutelaars en motorliefhebbers. Gratis starten.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is de beste motor onderhoud app?',
                        'answer' => 'GarageBook is een motor onderhoud app waarmee je beurten, reparaties, kilometerstanden, kosten, foto\'s en facturen van je motor bijhoudt in één tijdlijn. Het is gratis te starten en overdraagbaar bij verkoop.',
                    ],
                    [
                        'question' => 'Kan ik zelf uitgevoerd motoronderhoud vastleggen in een app?',
                        'answer' => 'Ja. In GarageBook leg je eigen werkzaamheden vast met datum, kilometerstand, beschrijving, gebruikte onderdelen en foto\'s. Voeg bonnetjes toe van aangeschafte materialen. Zo is ook zelf onderhoud aantoonbaar bij verkoop.',
                    ],
                    [
                        'question' => 'Helpt een motor onderhoud app bij de verkoopwaarde?',
                        'answer' => 'Ja. Kopers zijn bereid meer te betalen voor een motor met aantoonbare onderhoudshistorie. Een complete tijdlijn in GarageBook maakt je aanbieding geloofwaardiger en versnelt het verkoopproces.',
                    ],
                    [
                        'question' => 'Kan ik meerdere motoren bijhouden in één onderhoud-app?',
                        'answer' => 'Via GarageBook beheer je meerdere voertuigen in één overzicht. Iedere motor heeft een eigen tijdlijn met onderhoudsmomenten, foto\'s en documenten.',
                    ],
                    [
                        'question' => 'Is een motor onderhoud app gratis?',
                        'answer' => 'Starten met GarageBook is gratis. Je kunt direct je motor toevoegen en beginnen met het bijhouden van je onderhoudshistorie.',
                    ],
                ],
            ],
            [
                'slug' => 'motor-onderhoud-bijhouden',
                'title' => 'Motor onderhoud bijhouden: complete historie opbouwen',
                'content' => <<<'HTML'
<p>Motor onderhoud bijhouden is de basis voor een waardevolle rijder. Wie de onderhoudshistorie goed vastlegt, weet precies wat er gedaan is, voorkomt dure schade door gemiste beurten en verkoopt later veel gemakkelijker.</p>

<h2>Waarom motoronderhoud bijhouden loont</h2>
<p>Een complete onderhoudshistorie biedt drie directe voordelen: je weet precies wanneer de volgende beurt gepland staat, je kunt aantonen dat je motor goed is onderhouden bij verkoop, en je hebt bewijs als er ooit een discussie is over de staat van de motor.</p>

<h2>Wat moet je minimaal bijhouden?</h2>
<ul>
<li>Datum en kilometerstand van iedere beurt</li>
<li>Uitgevoerde werkzaamheden en gebruikte onderdelen</li>
<li>Type olie, vloeistoffen en specificaties</li>
<li>Kosten van werkzaamheden en materialen</li>
<li>Foto's, facturen en opmerkingen voor later</li>
</ul>

<h2>De 5 manieren waarop motorrijders onderhoud bijhouden</h2>
<p>Bijna iedereen begint simpel: geheugen, papier, Excel, notities of foto's in de telefoon. Het echte probleem is versnippering. Een centrale onderhoudshistorie maakt de informatie overdraagbaar en bruikbaar.</p>
<ol>
<li><strong>Geheugen</strong>: Werkt totdat je iets vergeet of je motor wisselt van eigenaar</li>
<li><strong>Papieren boekje</strong>: Solide, maar kwetsbaar voor verlies en bevat geen foto's</li>
<li><strong>Excel</strong>: Flexibel, maar mobiel onhandig en niet overdraagbaar</li>
<li><strong>Fotoalbum</strong>: Goed als aanvulling, maar geen gestructureerde tijdlijn</li>
<li><strong>Digitale app</strong>: Combineert alles in één overzicht, altijd beschikbaar</li>
</ol>

<h2>Welke onderhoudsmomenten zijn het meest kritisch?</h2>
<p>Voor motoren zijn dit de meest kritische onderhoudspunten om bij te houden:</p>
<ul>
<li>Olieverversing: type, specificatie en kilometerstand</li>
<li>Kettingset: ketting, voor- en achterwiel tandwiel</li>
<li>Remblokken en remvloeistof</li>
<li>Banden: merk, type, datum en rijgedrag bij wissel</li>
<li>Bougies, luchtfilter en contactpunten</li>
<li>Kleppen controleren: kritisch voor de meeste motortypen</li>
</ul>

<h2>Waarom onderhoud bijhouden geld waard kan zijn</h2>
<p>Een complete historie geeft vertrouwen, verkleint discussie en helpt om de staat van onderhoud beter te onderbouwen. Zeker als je zelf veel hebt gedaan, maakt bewijs het verhaal concreter en geloofwaardiger voor een koper.</p>

<h2>Motor onderhoud bijhouden met GarageBook</h2>
<p>GarageBook geeft je een digitale tijdlijn per motor. Iedere beurt, reparatie of aanpassing leg je vast met alle relevante details. Facturen en foto's upload je als bewijs. Bij verkoop deel je de complete tijdlijn.</p>

<p>Lees verder op <a href="/motor-onderhoud-app">motor onderhoud app</a>, <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a>, <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a> en <a href="/onderhoudshistorie-motor">onderhoudshistorie motor</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Motor onderhoud bijhouden – complete digitale historie | GarageBook',
                'meta_description' => 'Houd motor onderhoud, reparaties, kosten, kilometerstanden en bewijs overzichtelijk bij. Bouw een complete motorhistorie op die je kunt overdragen bij verkoop.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Waarom is het belangrijk om motoronderhoud bij te houden?',
                        'answer' => 'Motor onderhoud bijhouden geeft je overzicht over wanneer de volgende beurt gepland staat, helpt dure schade door gemiste beurten voorkomen en maakt je motor aantoonbaar goed onderhouden bij verkoop.',
                    ],
                    [
                        'question' => 'Wat moet ik minimaal bijhouden bij motoronderhoud?',
                        'answer' => 'Minimaal: datum, kilometerstand, uitgevoerde werkzaamheden, gebruikte onderdelen en olietype. Aanvullend: foto\'s, facturen en aantekeningen voor een completere en geloofwaardige history.',
                    ],
                    [
                        'question' => 'Hoe houd ik motoronderhoud digitaal bij?',
                        'answer' => 'Met GarageBook log je iedere onderhoudsactie met datum, kilometerstand, beschrijving, foto\'s en facturen. Zo bouw je een digitale tijdlijn op die altijd beschikbaar en overdraagbaar is.',
                    ],
                    [
                        'question' => 'Welke onderhoudspunten zijn het meest kritisch om bij te houden voor een motor?',
                        'answer' => 'De meest kritische punten: olieverversing (type en kilometerstand), kettingset vervanging, remblokken en -vloeistof, banden (type en datum), bougies en kleppen controleren.',
                    ],
                    [
                        'question' => 'Helpt een complete onderhoudshistorie bij de verkoop van mijn motor?',
                        'answer' => 'Absoluut. Een motor met complete, aantoonbare onderhoudshistorie is geloofwaardiger en verkoopt makkelijker. Kopers zijn bereid meer te betalen voor zekerheid over de onderhoudsstatus.',
                    ],
                ],
            ],
            [
                'slug' => 'onderhoudsboekje-motor',
                'title' => 'Onderhoudsboekje motor digitaal bijhouden',
                'content' => <<<'HTML'
<p>Vervang losse stempels, bonnetjes en notities door een digitaal onderhoudsboekje voor je motor. Bewaar onderhoud, kosten, kilometerstanden, foto's en bewijs op één plek – altijd beschikbaar, nooit kwijt.</p>

<h2>Waarom een digitaal onderhoudsboekje beter werkt voor je motor</h2>
<p>Niet alleen de stempel telt, maar het hele verhaal eromheen: wat is gedaan, bij welke kilometerstand, met welke onderdelen en met welk bewijs. Een papieren boekje wordt daarvoor al snel te klein en te beperkt.</p>
<p>Een digitaal onderhoudsboekje voor je motor biedt:</p>
<ul>
<li>Onbeperkte ruimte voor iedere beurt, reparatie en aanpassing</li>
<li>Foto's en facturen als bewijs naast iedere invoer</li>
<li>Altijd beschikbaar op je telefoon of laptop</li>
<li>Nooit kwijt of beschadigd</li>
<li>Overdraagbaar bij verkoop</li>
</ul>

<h2>Wat hoort er in het onderhoudsboekje van je motor?</h2>
<ul>
<li>Onderhoudsmomenten en kilometerstanden</li>
<li>Uitgevoerde werkzaamheden en gebruikte onderdelen</li>
<li>Type olie en andere vloeistoffen</li>
<li>Kosten per beurt of reparatie</li>
<li>Foto's van de toestand en werkzaamheden</li>
<li>Facturen, bonnetjes en garantiebewijzen</li>
<li>Toekomstige aandachtspunten en planningsmomenten</li>
</ul>

<h2>Zelf onderhoud gedaan? Zo bouw je toch bewijs op</h2>
<p>Door datum, kilometerstand, onderdelen, foto's en facturen van materialen vast te leggen kun je ook zelf uitgevoerd onderhoud goed onderbouwen. Dat is bij verkoop van grote waarde: een koper die ziet dat jij zorgvuldig hebt gedocumenteerd, krijgt vertrouwen – ook zonder officieel dealer-stempel.</p>

<h2>Motor verkopen met een digitaal onderhoudsboekje</h2>
<p>Bij verkoop is transparantie je sterkste troef. Deel de complete tijdlijn uit GarageBook met een potentiële koper. Ze zien direct wanneer welke beurt is gedaan, welke onderdelen zijn gebruikt en welke foto's de toestand vastleggen. Dat versnelt het verkoopproces en versterkt je onderhandelingspositie.</p>

<h2>Papieren onderhoudsboekje kwijt?</h2>
<p>Als je papieren boekje kwijt is, is overstappen op een digitaal alternatief de beste volgende stap. Reconstrueer zoveel mogelijk via facturen, garages en foto's, voer alles in bij GarageBook en bouw vanaf vandaag een nieuwe digitale history op. Lees meer op <a href="/onderhoudsboekje-kwijt">onderhoudsboekje kwijt</a>.</p>

<p>Lees ook <a href="/motor-onderhoud-app">motor onderhoud app</a>, <a href="/motor-onderhoud-bijhouden">motor onderhoud bijhouden</a>, <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a> en <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Onderhoudsboekje motor digitaal bijhouden | GarageBook',
                'meta_description' => 'Vervang je papieren motorboekje door een digitale onderhoudshistorie. Bewaar beurten, facturen, foto\'s en kilometres per motor met GarageBook. Gratis starten.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is een onderhoudsboekje voor een motor?',
                        'answer' => 'Een onderhoudsboekje voor een motor is een registratie van alle onderhoudswerkzaamheden die aan de motor zijn uitgevoerd: beurten, reparaties, kilometerstanden, gebruikte onderdelen en bewijsdocumenten. Een digitaal boekje bevat ook foto\'s en facturen.',
                    ],
                    [
                        'question' => 'Is een digitaal onderhoudsboekje voor een motor voldoende bij verkoop?',
                        'answer' => 'Ja. Een digitale history met facturen en foto\'s is inhoudelijk sterker dan alleen stempels in een papieren boekje. Kopers waarderen aantoonbaar bewijs meer dan een incompleet of leeg papieren boekje.',
                    ],
                    [
                        'question' => 'Hoe begin ik met een digitaal onderhoudsboekje voor mijn motor?',
                        'answer' => 'Registreer gratis bij GarageBook, voeg je motor toe en begin met het invoeren van bestaande beurten. Daarna log je nieuwe werkzaamheden direct na uitvoering en voeg je facturen en foto\'s toe als bewijs.',
                    ],
                    [
                        'question' => 'Wat doe ik als mijn papieren motorboekje kwijt is?',
                        'answer' => 'Benader garages en dealers voor digitale dossiers, zoek facturen en foto\'s, en gebruik GarageBook om een nieuwe digitale onderhoudshistorie op te bouwen. Lees meer stappen op de pagina onderhoudsboekje kwijt.',
                    ],
                    [
                        'question' => 'Kan ik ook zelf uitgevoerd motoronderhoud vastleggen?',
                        'answer' => 'Absoluut. In GarageBook leg je eigen werkzaamheden vast met datum, kilometerstand, gebruikte onderdelen, foto\'s en bonnetjes van materialen. Zo is ook zelf uitgevoerd onderhoud overdraagbaar bij verkoop.',
                    ],
                ],
            ],
        ];
    }
};
