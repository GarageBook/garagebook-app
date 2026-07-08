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

            $faqItems = $page['structured_data'] ?? null;
            unset($page['structured_data']);

            DB::table('pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'structured_data' => $faqItems ? json_encode($faqItems, JSON_UNESCAPED_UNICODE) : null,
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
                'title' => 'Onderhoudshistorie auto: complete gids voor eigenaren en kopers',
                'slug' => 'onderhoudshistorie-auto',
                'content' => <<<'HTML'
<p>De onderhoudshistorie van een auto legt vast wat er ooit met een voertuig is gedaan: van de eerste oliebeurt tot de laatste APK-keuring. Voor eigenaren, kopers en verkopers is een goed bijgehouden onderhoudshistorie een van de meest waardevolle documenten die je kunt hebben.</p>

<h2>Wat bevat een volledige onderhoudshistorie van een auto?</h2>
<p>Een complete onderhoudshistorie van een auto bevat meer dan alleen stempels uit een dealerboekje. Denk aan:</p>
<ul>
<li>Datum en kilometerstand van iedere onderhoudsbeurt</li>
<li>Uitgevoerde werkzaamheden: olie, remmen, distributie, banden, vloeistoffen</li>
<li>Gebruikte onderdelen en merken</li>
<li>Naam van de garage of uitvoerder</li>
<li>Facturen en bonnetjes als bewijs</li>
<li>Foto's van de werkzaamheden of toestand</li>
<li>Aandachtspunten en aanbevelingen voor volgend onderhoud</li>
</ul>

<h2>Waarom de onderhoudshistorie van je auto bijhouden?</h2>
<p>Wie de onderhoudshistorie bijhoudt, profiteert op drie momenten:</p>
<h3>1. Dagelijks gebruik</h3>
<p>Je weet precies wanneer de volgende beurt gepland staat en of er aandachtspunten zijn. Dat bespaart kosten en voorkomt schade door gemiste onderhoudsmomenten.</p>
<h3>2. Bij verkoop</h3>
<p>Een aantoonbare onderhoudshistorie geeft de koper vertrouwen en beïnvloedt de verkoopprijs positief. Auto's met bewijs van goed onderhoud zijn gemakkelijker te verkopen en roepen minder discussie op over de staat van het voertuig.</p>
<h3>3. Bij taxatie</h3>
<p>Verzekeraars en taxateurs hechten waarde aan aantoonbaar onderhoud. Een complete history kan de getaxeerde waarde verhogen.</p>

<h2>Onderhoudshistorie auto opbouwen met GarageBook</h2>
<p>GarageBook is een digitale omgeving waar je de complete onderhoudshistorie van je auto bijhoudt. Per voertuig leg je iedere onderhoudsactie vast met datum, kilometerstand, beschrijving, foto's en documenten. De tijdlijn groeit mee met je auto en is overdraagbaar bij verkoop.</p>
<ol>
<li>Registreer je auto met basisgegevens</li>
<li>Voeg bestaande beurten toe als starthistorie</li>
<li>Log nieuwe werkzaamheden direct na uitvoering</li>
<li>Upload facturen en foto's als bewijs</li>
<li>Deel de history bij verkoop of overdracht</li>
</ol>

<h2>Zelf onderhoud: ook dat vastleggen loont</h2>
<p>Wie zijn auto zelf onderhoudt, weet precies wat er gedaan is maar heeft geen officieel stempel. Met GarageBook leg je eigen werkzaamheden vast met onderdelen, foto's en bonnetjes van materialen. Zo is ook zelf uitgevoerd onderhoud aantoonbaar bij verkoop of taxatie.</p>

<h2>Onderhoudshistorie auto controleren bij aankoop</h2>
<p>Als koper is de eerste stap altijd het controleren van de beschikbare history. Let op gaten in de tijdlijn, kilometerstandsinconsistenties en ontbrekende grote onderhoudsmomenten zoals distributieriem of koelwater. Een auto zonder aantoonbare history is een groter financieel risico.</p>

<h2>Ontbrekende onderhoudshistorie: wat kun je doen?</h2>
<p>Als je auto een gedeeltelijk lege history heeft, kun je stapsgewijs reconstrueren. Benader vorige garages voor hun digitale dossiers, zoek facturen en bankafschriften, en gebruik foto's uit je telefoonalbum. Lees meer op <a href="/onderhoudsboekje-kwijt">onderhoudsboekje kwijt</a>.</p>
<p>Gerelateerde pagina's: <a href="/onderhoudsgeschiedenis-auto">onderhoudsgeschiedenis auto</a>, <a href="/digitaal-onderhoudsboekje-auto">digitaal onderhoudsboekje auto</a>, <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Onderhoudshistorie auto – complete gids voor eigenaren | GarageBook',
                'meta_description' => 'Alles over de onderhoudshistorie van een auto: wat het inhoudt, waarom het waardevol is bij verkoop en taxatie, en hoe je een volledige digitale history opbouwt.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is de onderhoudshistorie van een auto?',
                        'answer' => 'De onderhoudshistorie van een auto is het complete overzicht van alle onderhoudswerkzaamheden die ooit aan het voertuig zijn uitgevoerd, inclusief data, kilometerstanden, uitgevoerde werkzaamheden, gebruikte onderdelen en bewijsdocumenten zoals facturen en foto\'s.',
                    ],
                    [
                        'question' => 'Hoe bouw ik een onderhoudshistorie op als ik het boekje niet meer heb?',
                        'answer' => 'Zoek naar bestaande facturen van garages, bonnetjes van onderdelen en foto\'s van werkzaamheden. Benader garages en dealers waar je eerder onderhoud hebt laten uitvoeren – zij hebben vaak nog digitale dossiers. Breng alles samen in een digitale tool zoals GarageBook.',
                    ],
                    [
                        'question' => 'Maakt een complete onderhoudshistorie je auto meer waard?',
                        'answer' => 'Ja. Kopers zijn bereid meer te betalen voor een auto waarbij aantoonbaar goed onderhoud is gepleegd. Gaten in de onderhoudshistorie leiden tot lagere biedingen en meer discussie over de staat van het voertuig.',
                    ],
                    [
                        'question' => 'Kan ik ook zelf uitgevoerd onderhoud vastleggen in de onderhoudshistorie?',
                        'answer' => 'Ja. Via GarageBook leg je eigen werkzaamheden vast met datum, kilometerstand, beschrijving, onderdelen, foto\'s en bonnetjes. Zo is ook zelf uitgevoerd onderhoud aantoonbaar bij verkoop of taxatie.',
                    ],
                    [
                        'question' => 'Hoe lang moet ik de onderhoudshistorie van mijn auto bewaren?',
                        'answer' => 'Bewaar de complete onderhoudshistorie zolang je de auto bezit en draag hem over aan de nieuwe eigenaar bij verkoop. Digitale opslag via GarageBook zorgt dat de history nooit verloren gaat.',
                    ],
                ],
            ],
            [
                'title' => 'Onderhoudsgeschiedenis auto opvragen en opbouwen',
                'slug' => 'onderhoudsgeschiedenis-auto',
                'content' => <<<'HTML'
<p>De onderhoudsgeschiedenis van een auto is het complete verhaal van wat er ooit met het voertuig is gedaan. Van de eerste servicebeurt tot de meest recente APK: wie de onderhoudsgeschiedenis kent, kent de auto. Op deze pagina lees je hoe je de history opvraagt, controleert en zelf opbouwt.</p>

<h2>Wat is de onderhoudsgeschiedenis van een auto?</h2>
<p>Onderhoudsgeschiedenis en onderhoudshistorie zijn synoniemen. Ze beschrijven het totaaloverzicht van werkzaamheden, reparaties, keuringen en upgrades die een voertuig heeft ondergaan. Een volledige onderhoudsgeschiedenis bevat:</p>
<ul>
<li>Alle periodieke beurten met datum en kilometerstand</li>
<li>Reparaties en vervanging van onderdelen</li>
<li>APK-keuringen en uitkomsten</li>
<li>Aanpassingen, upgrades en accessoires</li>
<li>Schade-incidents en hersteldocumentatie</li>
</ul>

<h2>Onderhoudsgeschiedenis opvragen bij aankoop van een auto</h2>
<p>Bij de aankoop van een tweedehands auto wil je de onderhoudsgeschiedenis controleren. Dit zijn de beste manieren:</p>
<h3>Via de verkoper</h3>
<p>Vraag het originele onderhoudsboekje op met alle stempels van erkende dealers en garages. Vraag ook naar losse facturen en bonnetjes voor werkzaamheden die buiten de dealer zijn uitgevoerd.</p>
<h3>Via het dealernetwerk</h3>
<p>Als de auto altijd bij een dealernetwerk is onderhouden, kan de dealer de history opvragen uit het digitale systeem van de fabrikant. Dit is mogelijk voor veel Europese merkdealers.</p>
<h3>Via digitale kentekencontroles</h3>
<p>Kentekencontroles via de RDW geven beperkte maar waardevolle informatie: APK-keuringen, schademeldingen en eigendomshistorie zijn vaak beschikbaar.</p>

<h2>Gaten in de onderhoudsgeschiedenis: wat betekent dat?</h2>
<p>Gaten in de onderhoudsgeschiedenis zijn een risicosignaal. Ze kunnen betekenen dat onderhoud is overgeslagen (wat schade kan veroorzaken), dat onderhoud niet is gedocumenteerd (wat de waarde verlaagt), of dat informatie bewust wordt achtergehouden. Wees kritisch en vraag altijd om een verklaring.</p>

<h2>Zelf je onderhoudsgeschiedenis opbouwen</h2>
<p>Of je nu een nieuwe auto hebt of een tweedehands auto met onvolledige history: je kunt vandaag nog beginnen met het opbouwen van je eigen digitale onderhoudsgeschiedenis. Met GarageBook voeg je iedere werkzaamheid toe met alle relevante details:</p>
<ul>
<li>Datum en kilometerstand</li>
<li>Beschrijving van de werkzaamheden</li>
<li>Kosten en gebruikte materialen</li>
<li>Foto's en facturen als bewijs</li>
</ul>
<p>Zo bouw je stap voor stap een complete digitale onderhoudsgeschiedenis op die je later overdraagt bij verkoop.</p>

<h2>Onderhoudsgeschiedenis overdragen bij verkoop</h2>
<p>Een van de sterkste troeven van een auto met goede onderhoudsgeschiedenis is overdraagbaarheid. Met een digitale tool deel je de complete history met een potentiële koper, liefst al voor de bezichtiging. Dat creëert vertrouwen en versnelt het verkoopproces.</p>
<p>Meer lezen? Bekijk <a href="/onderhoudshistorie-auto">onderhoudshistorie auto</a>, <a href="/digitaal-onderhoudsboekje-auto">digitaal onderhoudsboekje auto</a> en <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Onderhoudsgeschiedenis auto – opvragen, controleren en opbouwen | GarageBook',
                'meta_description' => 'Leer hoe je de onderhoudsgeschiedenis van een auto opvraagt bij dealers en garages, hoe je gaten herkent en hoe je zelf een digitale onderhoudsgeschiedenis opbouwt.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is het verschil tussen onderhoudsgeschiedenis en onderhoudshistorie?',
                        'answer' => 'Onderhoudsgeschiedenis en onderhoudshistorie zijn synoniemen. Beide termen beschrijven het complete overzicht van alle onderhoudswerkzaamheden die aan een voertuig zijn uitgevoerd.',
                    ],
                    [
                        'question' => 'Hoe vraag ik de onderhoudsgeschiedenis op van een tweedehands auto?',
                        'answer' => 'Vraag de verkoper om het originele onderhoudsboekje en losse facturen. Benader het dealernetwerk als de auto altijd bij de dealer is onderhouden. Gebruik de RDW voor kentekencontrole op APK-keuringen en eigendomshistorie.',
                    ],
                    [
                        'question' => 'Wat doe ik als de onderhoudsgeschiedenis ontbreekt?',
                        'answer' => 'Begin zelf met opbouwen vanaf het moment van aankoop. Benader vorige garages voor eventuele dossiers. Gebruik GarageBook om alle beschikbare informatie samen te brengen in een digitale onderhoudsgeschiedenis.',
                    ],
                    [
                        'question' => 'Zijn gaten in de onderhoudsgeschiedenis altijd een probleem?',
                        'answer' => 'Gaten zijn een risicosignaal maar niet altijd fataal. Sommige eigenaren hebben onderhoud goed uitgevoerd maar slecht gedocumenteerd. Vraag altijd om een verklaring en beoordeel de algehele toestand van het voertuig mee.',
                    ],
                    [
                        'question' => 'Helpt een complete onderhoudsgeschiedenis bij de APK?',
                        'answer' => 'Een complete onderhoudsgeschiedenis maakt aankomende APK-keuringen beter voorspelbaar. Je weet welk onderhoud recent is gedaan en kunt anticiperen op wat er mogelijk vervangen moet worden.',
                    ],
                ],
            ],
            [
                'title' => 'Onderhoudshistorie motor digitaal bijhouden en overdragen',
                'slug' => 'onderhoudshistorie-motor',
                'content' => <<<'HTML'
<p>De onderhoudshistorie van je motor is het bewijs van hoe goed je voertuig is onderhouden. Voor motorrijders is dit even belangrijk als voor autorijders – en soms nog waardevoller, omdat motoren vaker tweedehands verkopen met ontbrekende documentatie.</p>

<h2>Waarom de onderhoudshistorie van je motor bijhouden?</h2>
<p>Een motor met complete onderhoudshistorie is meer waard, makkelijker te verkopen en biedt de koper meer zekerheid. Voor de eigenaar is het een handig instrument om beurten en intervallen bij te houden en te weten wanneer welk onderhoud gepland staat.</p>

<h2>Wat hoort in de onderhoudshistorie van een motor?</h2>
<ul>
<li>Periodieke oliebeurten met datum en kilometerstand</li>
<li>Kettingvervanging en -afstelling</li>
<li>Remblokken en remvloeistof</li>
<li>Banden (type, merk, datum)</li>
<li>Versnellingsbak en koelvloeistof</li>
<li>Bougies en luchtfilter</li>
<li>APK-keuringen</li>
<li>Eventuele reparaties of schade-incidenten</li>
<li>Upgrades, accessoires en aanpassingen</li>
</ul>

<h2>Hoe bouw je een digitale onderhoudshistorie op voor je motor?</h2>
<p>GarageBook maakt het bijhouden van de onderhoudshistorie van je motor eenvoudig. Per motor maak je een eigen tijdlijn aan waar iedere beurt, reparatie of aanpassing wordt vastgelegd.</p>
<ol>
<li>Maak een voertuigprofiel aan voor je motor</li>
<li>Voeg bestaande beurten toe als starthistorie</li>
<li>Log nieuwe werkzaamheden direct na uitvoering</li>
<li>Upload facturen van de werkplaats of bonnetjes van eigen materialen</li>
<li>Voeg foto's toe van bijzondere werkzaamheden of toestand van de motor</li>
</ol>

<h2>Zelf onderhoud doen en toch een sterke history opbouwen</h2>
<p>Motorrijders die zelf onderhoud doen, hebben geen dealer-stempel maar weten precies wat er gedaan is. Door je eigen werkzaamheden vast te leggen met GarageBook bouw je toch een aantoonbare onderhoudshistorie op. Noteer het gebruikte olienummer, de kilometerstand, de gebruikte onderdelen en maak een foto van de motorconditie. Dat is overtuigender dan een leeg boekje bij verkoop.</p>

<h2>Onderhoudshistorie motor bij tweedehands aankoop controleren</h2>
<p>Controleer bij aankoop van een tweedehands motor altijd de onderhoudshistorie. Let op:</p>
<ul>
<li>Zijn alle aanbevolen beurten gevolgd (zie instructies van de fabrikant)?</li>
<li>Is de kilometerstand consistent met de intervallen?</li>
<li>Zijn er aantoonbare gaten in de history?</li>
<li>Is er documentatie van grotere werkzaamheden zoals kettingset of kleppen?</li>
</ul>

<h2>Onderhoudshistorie motor overdragen bij verkoop</h2>
<p>Wie zijn motor verkoopt met een complete, digitale onderhoudshistorie staat sterker. Deel de GarageBook-tijdlijn als onderdeel van de verkoop. Dat maakt je aanbieding transparanter en geeft de koper vertrouwen.</p>
<p>Lees ook <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a>, <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a>, <a href="/motor-onderhoud-bijhouden">motor onderhoud bijhouden</a> en <a href="/motor-onderhoud-app">motor onderhoud app</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Onderhoudshistorie motor – digitaal bijhouden en overdragen | GarageBook',
                'meta_description' => 'Bouw een complete digitale onderhoudshistorie van je motor op met GarageBook. Van oliebeurt tot ketting: alles op één plek bewaard en overdraagbaar bij verkoop.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is de onderhoudshistorie van een motor?',
                        'answer' => 'De onderhoudshistorie van een motor is het complete overzicht van alle onderhoudswerkzaamheden die ooit aan het voertuig zijn uitgevoerd, inclusief oliebeurten, remmen, banden, ketting, APK en eventuele reparaties.',
                    ],
                    [
                        'question' => 'Hoe bewaar ik de onderhoudshistorie van mijn motor digitaal?',
                        'answer' => 'Met GarageBook leg je per onderhoudsmoment alle details vast: datum, kilometerstand, beschrijving van werkzaamheden, facturen en foto\'s. Zo bouw je een complete digitale onderhoudshistorie op die je later kunt overdragen bij verkoop.',
                    ],
                    [
                        'question' => 'Is onderhoudshistorie van een motor net zo belangrijk als bij een auto?',
                        'answer' => 'Ja, en soms nog meer. Motoren worden vaker tweedehands verkocht met ontbrekende documentatie. Een motor met aantoonbare onderhoudshistorie onderscheidt zich duidelijk en kan sneller en voor een betere prijs verkopen.',
                    ],
                    [
                        'question' => 'Kan ik zelf onderhoud doen en toch een goede onderhoudshistorie opbouwen?',
                        'answer' => 'Absoluut. Leg je eigen werkzaamheden vast met datum, kilometerstand, beschrijving, gebruikte onderdelen en foto\'s. Voeg bonnetjes toe van materialen. Zo is zelf uitgevoerd onderhoud aantoonbaar en overdraagbaar.',
                    ],
                    [
                        'question' => 'Wat zijn de meest kritische onderhoudspunten om vast te leggen voor een motor?',
                        'answer' => 'Oliebeurten (met type olie), kettingset vervanging, remblokken en -vloeistof, banden (type en datum), kleppen controleren en bougies. Dit zijn de punten waar kopers en taxateurs het meest naar vragen.',
                    ],
                ],
            ],
            [
                'title' => 'Onderhoudsboekje kwijt? Dit zijn je opties',
                'slug' => 'onderhoudsboekje-kwijt',
                'content' => <<<'HTML'
<p>Je onderhoudsboekje kwijt en je weet niet hoe je de onderhoudshistorie moet reconstrueren? Dat is vervelend, maar er zijn concrete stappen die je kunt nemen om zoveel mogelijk bewijs terug te vinden – en te voorkomen dat je dit ooit nog overkomt.</p>

<h2>Stap 1: Zoek facturen en bonnetjes</h2>
<p>Doorzoek je archieven op papieren facturen van garages, bonnetjes van onderdelen en bankafschriften met betalingen aan garages of autodealers. Veel onderhoudsacties zijn terug te vinden als betalingen in je bankhistorie, ook als het papieren bewijs kwijt is.</p>

<h2>Stap 2: Benader vorige garages en dealers</h2>
<p>Garages en erkende dealers bewaren hun klantgegevens en werkzaamheden vrijwel altijd digitaal. Neem contact op met de garages waar je eerder onderhoud hebt laten uitvoeren. Zij kunnen je in veel gevallen een overzicht geven van de uitgevoerde werkzaamheden, inclusief datum en kilometerstand.</p>

<h2>Stap 3: Check digitale dossiers bij merkdealers</h2>
<p>Als je altijd bij een merkdealer hebt laten onderhouden, heeft het dealernetwerk van de fabrikant vrijwel zeker een digitaal dossier. Contact opnemen met de dealer en je voertuigidentificatienummer (VIN) opgeven is de snelste route naar bestaande onderhoudsdata.</p>

<h2>Stap 4: Gebruik foto's uit je telefoonalbum</h2>
<p>Zoek in je telefoonalbum naar foto's van werkzaamheden, garagebezoeken, nieuwe banden of andere momenten gerelateerd aan onderhoud. Datum en locatie van foto's helpen om een tijdlijn te reconstrueren.</p>

<h2>Stap 5: Bouw een nieuwe digitale onderhoudshistorie op</h2>
<p>Verzamel alles wat je gevonden hebt en voer het in in GarageBook. Zo creëer je een nieuwe, digitale onderhoudshistorie die je nooit meer kwijtraakt. Voeg ook toekomstige beurten direct toe na uitvoering.</p>

<h2>Een nieuw universeel onderhoudsboekje aanschaffen: werkt dat?</h2>
<p>Je kunt een leeg universeel onderhoudsboekje aanschaffen om nieuwe stempels in te laten zetten. Maar dit lost twee problemen niet op: de ontbrekende history van het verleden blijft een gat, en het risico dat je het opnieuw kwijtraakt is aanwezig. Een digitale oplossing is voor beide problemen robuuster.</p>

<h2>Hoe voorkom je dat je onderhoudsboekje ooit nog kwijtraakt?</h2>
<p>De beste oplossing is overstappen op een digitale onderhoudshistorie via GarageBook. Een digitale history:</p>
<ul>
<li>Kan nooit fysiek kwijtraken</li>
<li>Is altijd beschikbaar via je telefoon of laptop</li>
<li>Bevat meer bewijs dan een stempel: foto's, facturen, aantekeningen</li>
<li>Is overdraagbaar bij verkoop zonder het risico van verlies</li>
</ul>

<h2>Wat zegt een leeg onderhoudsboekje over de waarde van je voertuig?</h2>
<p>Kopers worden kritischer als het onderhoudsboekje ontbreekt of leeg is. Ze moeten de staat van het voertuig inschatten zonder documentatiebewijs, wat kan leiden tot een lagere bieding of afhaken. Wie een goede, aantoonbare history kan overleggen – ook als die digitaal is – staat sterker.</p>
<p>Meer lezen? Bekijk <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>, <a href="/digitaal-onderhoudsboekje-auto">digitaal onderhoudsboekje auto</a>, <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a> en <a href="/onderhoudshistorie-auto">onderhoudshistorie auto</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Onderhoudsboekje kwijt – wat nu? Reconstrueer je history | GarageBook',
                'meta_description' => 'Ben je je onderhoudsboekje kwijt? Volg deze 5 stappen om ontbrekende onderhoudshistorie te reconstrueren en voorkom dat je het ooit nog verliest met GarageBook.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Mijn onderhoudsboekje is kwijt. Wat moet ik doen?',
                        'answer' => 'Zoek bestaande facturen van garages, check bankafschriften op betalingen aan garages, benader garages en dealers voor hun digitale dossiers en zoek in je telefoonalbum naar foto\'s van werkzaamheden. Breng alles samen in een digitale tool zoals GarageBook.',
                    ],
                    [
                        'question' => 'Kan ik een nieuw onderhoudsboekje aanschaffen als mijn oude kwijt is?',
                        'answer' => 'Ja, een universeel onderhoudsboekje is te koop bij autoparts-winkels. Maar het lost de ontbrekende history van het verleden niet op. Een betere langetermijnoplossing is overstappen op een digitale onderhoudshistorie.',
                    ],
                    [
                        'question' => 'Verlaagt een kwijt onderhoudsboekje de waarde van mijn auto of motor?',
                        'answer' => 'Ontbrekende documentatie maakt kopers onzekerder, wat kan leiden tot lagere biedingen of afhaken. Een reconstructie van de history via facturen, foto\'s en garagebevestigingen helpt om dit risico te beperken.',
                    ],
                    [
                        'question' => 'Hoe kan ik voorkomen dat ik mijn onderhoudsboekje ooit nog kwijtraak?',
                        'answer' => 'Ga over op een digitale onderhoudshistorie via GarageBook. Een digitale history kan nooit fysiek kwijtraken, is altijd beschikbaar en bevat meer bewijs dan een papieren stempel.',
                    ],
                    [
                        'question' => 'Bewaren garages mijn onderhoudsgegevens digitaal?',
                        'answer' => 'De meeste erkende dealers en grotere garages bewaren klantgegevens en werkorders digitaal. Neem contact op met garages waar je eerder onderhoud hebt laten uitvoeren en geef je voertuigidentificatienummer (VIN) op voor de beste resultaten.',
                    ],
                ],
            ],
            [
                'title' => 'Digitaal onderhoudsboekje voor je auto',
                'slug' => 'digitaal-onderhoudsboekje-auto',
                'content' => <<<'HTML'
<p>Een digitaal onderhoudsboekje voor je auto vervangt het papieren boekje door een complete digitale tijdlijn van onderhoud, reparaties, documenten en bewijsmateriaal. Altijd beschikbaar, nooit kwijt, overdraagbaar bij verkoop.</p>

<h2>Waarom kiezen voor een digitaal onderhoudsboekje voor je auto?</h2>
<p>Het papieren onderhoudsboekje heeft beperkingen die steeds duidelijker worden. Een digitaal alternatief biedt:</p>
<ul>
<li><strong>Altijd beschikbaar</strong>: Geen fysiek boekje dat je vergeet of kwijtraakt</li>
<li><strong>Meer bewijs</strong>: Foto's, facturen en aantekeningen naast iedere beurt</li>
<li><strong>Meerdere voertuigen</strong>: Alle auto's in één overzicht</li>
<li><strong>Overdraagbaar</strong>: Deel je history digitaal bij verkoop</li>
<li><strong>Zelf onderhoud vastleggen</strong>: Ook eigen werkzaamheden worden aantoonbaar</li>
</ul>

<h2>Wat leg je vast in een digitaal onderhoudsboekje voor je auto?</h2>
<p>Alles wat je ooit met je auto gedaan hebt of hebt laten doen hoort erin:</p>
<ul>
<li>Periodieke beurten: olie, filters, vloeistoffen</li>
<li>Grotere onderhoudsmomenten: distributie, koppeling, remmen</li>
<li>APK-keuringen en uitslagen</li>
<li>Bandensets: merk, maat en datum</li>
<li>Reparaties en vervangen onderdelen</li>
<li>Upgrades en accessoires</li>
<li>Schade en herstelwerk</li>
</ul>

<h2>Hoe werkt GarageBook als digitaal onderhoudsboekje voor je auto?</h2>
<p>GarageBook is gebouwd als centrale plek voor de complete voertuiggeschiedenis. Je maakt een voertuigprofiel aan voor je auto, voegt bestaande history toe als startpunt en logt nieuwe werkzaamheden direct na uitvoering. Alle data – inclusief foto's en documenten – staat gebundeld in één digitale tijdlijn.</p>
<ol>
<li>Gratis registreren via <a href="/start">app.garagebook.nl/start</a></li>
<li>Auto toevoegen met merk, model en bouwjaar</li>
<li>Bestaande onderhoudsmomenten invoeren als starthistorie</li>
<li>Nieuwe beurten en reparaties bijhouden</li>
<li>Facturen en foto's uploaden als bewijs</li>
</ol>

<h2>Digitaal onderhoudsboekje auto bij verkoop</h2>
<p>Een van de sterkste momenten voor een digitaal onderhoudsboekje is bij de verkoop van je auto. Je kunt de complete history aantonen, inclusief foto's van uitgevoerde werkzaamheden en originele facturen. Dat geeft kopers vertrouwen en maakt jou als verkoper geloofwaardiger.</p>

<h2>Is een digitaal onderhoudsboekje voor een auto juridisch geldig?</h2>
<p>Er is geen wettelijke verplichting om een papieren onderhoudsboekje bij te houden. Een digitale history met facturen en foto's als bewijs is inhoudelijk minstens zo sterk als een papieren stempel – en in veel gevallen sterker, omdat het meer details bevat.</p>
<p>Bekijk ook <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>, <a href="/onderhoudshistorie-auto">onderhoudshistorie auto</a>, <a href="/onderhoudsgeschiedenis-auto">onderhoudsgeschiedenis auto</a> en <a href="/onderhoudsboekje-kwijt">onderhoudsboekje kwijt</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Digitaal onderhoudsboekje auto – start gratis | GarageBook',
                'meta_description' => 'Vervang je papieren onderhoudsboekje door een digitale versie voor je auto. Bewaar beurten, facturen, foto\'s en kilometerstanden op één plek met GarageBook.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is een digitaal onderhoudsboekje voor een auto?',
                        'answer' => 'Een digitaal onderhoudsboekje voor een auto is een digitale registratie van alle onderhoudswerkzaamheden die aan je auto zijn uitgevoerd. In tegenstelling tot een papieren boekje bevat een digitale versie ook foto\'s, facturen en aantekeningen, en is het nooit kwijt te raken.',
                    ],
                    [
                        'question' => 'Is een digitaal onderhoudsboekje voor een auto geldig bij verkoop?',
                        'answer' => 'Ja. Er is geen wettelijke verplichting voor een papieren boekje. Een digitale history met facturen en foto\'s als bewijs is inhoudelijk minstens zo sterk en bevat vaak meer details dan een papieren stempel.',
                    ],
                    [
                        'question' => 'Hoe begin ik met een digitaal onderhoudsboekje voor mijn auto?',
                        'answer' => 'Registreer gratis bij GarageBook, maak een voertuigprofiel aan voor je auto en voeg bestaande onderhoudsmomenten toe als startpunt. Daarna log je nieuwe beurten direct na uitvoering.',
                    ],
                    [
                        'question' => 'Kan ik ook zelf uitgevoerd onderhoud vastleggen in een digitaal onderhoudsboekje?',
                        'answer' => 'Ja. GarageBook laat je eigen werkzaamheden vastleggen met datum, kilometerstand, beschrijving, gebruikte onderdelen, foto\'s en bonnetjes van materialen. Zo is ook zelf onderhoud aantoonbaar bij verkoop of taxatie.',
                    ],
                    [
                        'question' => 'Wat kost een digitaal onderhoudsboekje voor mijn auto via GarageBook?',
                        'answer' => 'Starten met GarageBook is gratis. Je kunt direct je auto toevoegen en beginnen met het bijhouden van je onderhoudshistorie zonder kosten.',
                    ],
                ],
            ],
            [
                'title' => 'Digitaal onderhoudsboekje voor je motor',
                'slug' => 'digitaal-onderhoudsboekje-motor',
                'content' => <<<'HTML'
<p>Een digitaal onderhoudsboekje voor je motor geeft je één centrale plek voor alle beurten, reparaties, upgrades, kosten en bewijs. Geen losse bonnetjes meer, geen risico op verlies, altijd beschikbaar op je telefoon.</p>

<h2>Wat is een digitaal onderhoudsboekje voor een motor?</h2>
<p>Een digitaal onderhoudsboekje voor een motor is een online omgeving waar je per motor de complete onderhoudshistorie bijhoudt. Het is een directe opvolger van het papieren stempelboekje, maar uitgebreider: naast beurten leg je ook foto's, facturen, opmerkingen en toekomstige aandachtspunten vast.</p>

<h2>Voordelen van een digitaal motorboekje ten opzichte van papier</h2>
<ul>
<li>Nooit kwijt of beschadigd</li>
<li>Foto's en facturen als bewijs naast iedere beurt</li>
<li>Zelf onderhoud aantoonbaar vastleggen</li>
<li>Altijd beschikbaar, ook onderweg</li>
<li>Overdraagbaar bij verkoop van je motor</li>
<li>Meerdere motoren beheerbaar in één overzicht</li>
</ul>

<h2>Wat leg je vast in een digitaal onderhoudsboekje voor je motor?</h2>
<p>Ieder onderhoudsmoment verdient een eigen registratie. Denk aan:</p>
<ul>
<li>Olie- en filterwissels</li>
<li>Kettingset: ketting, tandwiel voor en achter</li>
<li>Remblokken en remvloeistof</li>
<li>Banden: merk, maat, datum en rijgedrag</li>
<li>Bougies, luchtfilter en contactpunten</li>
<li>APK-keuringen</li>
<li>Reparaties na rijschade of slijtage</li>
<li>Upgrades en accessoires</li>
</ul>

<h2>Hoe gebruik je GarageBook als digitaal motorboekje?</h2>
<p>GarageBook is speciaal gebouwd voor motorrijders die hun voertuig serieus bijhouden. Per motor maak je een tijdlijn aan die groeit met iedere beurt.</p>
<ol>
<li>Registreer gratis via <a href="/start">app.garagebook.nl/start</a></li>
<li>Voeg je motor toe met merk, model en bouwjaar</li>
<li>Voer bestaande beurten in als starthistorie</li>
<li>Log nieuwe beurten direct na uitvoering</li>
<li>Voeg facturen, foto's en aantekeningen toe</li>
</ol>

<h2>Digitaal onderhoudsboekje motor bij verkoop</h2>
<p>Wie zijn motor verkoopt met een digitale onderhoudshistorie geeft de koper veel meer vertrouwen dan een leeg boekje of losse bonnetjes. Deel de GarageBook-tijdlijn als onderdeel van de verkoop voor een sterkere positie.</p>

<h2>Zelf onderhoud doen: ook dan een goede boekhouding</h2>
<p>Motorrijders die zelf onderhoud uitvoeren, missen vaak het gevoel een 'officieel' boekje te kunnen overleggen. Met GarageBook maak je zelf uitgevoerd onderhoud aantoonbaar door datum, kilometerstand, gebruikte materialen en foto's vast te leggen. Dat is krachtiger dan een leeg dealer-boekje.</p>
<p>Bekijk ook <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>, <a href="/onderhoudsboekje-motor">onderhoudsboekje motor</a>, <a href="/motor-onderhoud-app">motor onderhoud app</a> en <a href="/onderhoudshistorie-motor">onderhoudshistorie motor</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Digitaal onderhoudsboekje motor – start gratis | GarageBook',
                'meta_description' => 'Start gratis met een digitaal onderhoudsboekje voor je motor. Houd beurten, facturen, kilometerstanden en foto\'s bij op één plek met GarageBook.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is het beste digitale onderhoudsboekje voor een motor?',
                        'answer' => 'GarageBook is een digitale onderhoudsomgeving speciaal voor motorrijders. Je legt beurten vast met datum, kilometerstand, beschrijving, foto\'s en facturen. Het is gratis te starten en overdraagbaar bij verkoop.',
                    ],
                    [
                        'question' => 'Is een digitaal onderhoudsboekje voor een motor voldoende bij verkoop?',
                        'answer' => 'Ja. Een digitale history met facturen en foto\'s is inhoudelijk sterker dan alleen een stempel in een papieren boekje. Kopers waarderen transparantie en aantoonbaar bewijs meer dan een leeg of incompleet papieren boekje.',
                    ],
                    [
                        'question' => 'Kan ik meerdere motoren bijhouden in één digitaal onderhoudsboekje?',
                        'answer' => 'Via GarageBook beheer je meerdere voertuigen, motoren én auto\'s, in één overzicht. Iedere motor heeft een eigen tijdlijn.',
                    ],
                    [
                        'question' => 'Hoe leg ik zelf uitgevoerd motoronderhoud digitaal vast?',
                        'answer' => 'In GarageBook log je iedere onderhoudsactie met datum, kilometerstand, beschrijving, gebruikte onderdelen en foto\'s. Voeg bonnetjes toe van materialen die je hebt aangeschaft. Zo is ook eigen onderhoud aantoonbaar.',
                    ],
                    [
                        'question' => 'Is een digitaal onderhoudsboekje voor een motor gratis?',
                        'answer' => 'Starten met GarageBook is gratis. Je kunt direct je motor toevoegen en beginnen met je onderhoudshistorie zonder kosten.',
                    ],
                ],
            ],
            [
                'title' => 'Universeel onderhoudsboekje: voordelen, nadelen en alternatieven',
                'slug' => 'universeel-onderhoudsboekje',
                'content' => <<<'HTML'
<p>Een universeel onderhoudsboekje is een generiek papieren boekje zonder merk of voertuigspecifieke gegevens, bedoeld als vervanging of aanvulling op het originele onderhoudsboekje. Maar heeft het nog zin in 2026? En wat zijn de alternatieven?</p>

<h2>Wat is een universeel onderhoudsboekje?</h2>
<p>Een universeel onderhoudsboekje is een generiek, te koop aangeboden papieren boekje dat je kunt gebruiken voor elk voertuig. Er staan stempelruimtes en invulvakken in, maar geen fabrikantspecifieke onderhoudsinstructies of VIN-koppeling. Ze zijn te koop bij autoparts-winkels, tankstations en online.</p>

<h2>Wanneer wordt een universeel onderhoudsboekje gebruikt?</h2>
<p>Een universeel boekje wordt vaak aangeschaft wanneer:</p>
<ul>
<li>Het originele onderhoudsboekje kwijt is</li>
<li>Het origineel vol of beschadigd is</li>
<li>De auto of motor uit het buitenland komt zonder Nederlandse documentatie</li>
<li>Men zelf onderhoud wil documenteren buiten de dealer om</li>
</ul>

<h2>Wat zijn de beperkingen van een universeel onderhoudsboekje?</h2>
<p>Een universeel boekje heeft serieuze beperkingen:</p>
<ul>
<li><strong>Geen fabrikantspecifieke waarde</strong>: Erkende dealers accepteren een universeel boekje niet als vervanger van het origineel voor garantiedoeleinden</li>
<li><strong>Beperkt bewijs</strong>: Een stempel zonder factuur of beschrijving van werkzaamheden zegt weinig over wat er daadwerkelijk is gedaan</li>
<li><strong>Geen foto's of bijlagen</strong>: Je kunt geen visueel bewijs toevoegen</li>
<li><strong>Verliesrisico</strong>: Net als het origineel kan het kwijtraken of beschadigen</li>
<li><strong>Niet overdraagbaar</strong>: Geen digitale export of deelmogelijkheid</li>
</ul>

<h2>Waarom is een universeel onderhoudsboekje steeds minder waardevol?</h2>
<p>Kopers zijn steeds sceptischer over universele boekjes, omdat ze gemakkelijk zijn aan te schaffen als 'opvulling' van een ontbrekende history. Een boekje vol stempels zonder facturen of beschrijvingen geeft kopers weinig zekerheid over de daadwerkelijke toestand van het voertuig.</p>

<h2>Wat is een beter alternatief voor een universeel onderhoudsboekje?</h2>
<p>In plaats van een universeel papieren boekje, kies je beter voor een digitale onderhoudshistorie via GarageBook. De voordelen:</p>
<ul>
<li>Bevat meer informatie dan een stempel: foto's, facturen, beschrijvingen en opmerkingen</li>
<li>Nooit kwijt of beschadigd</li>
<li>Altijd beschikbaar op je telefoon of laptop</li>
<li>Overdraagbaar bij verkoop zonder verliesrisico</li>
<li>Geloofwaardiger bij kopers die vragen om aantoonbaar bewijs</li>
</ul>

<h2>Universeel onderhoudsboekje kopen of digitaal gaan?</h2>
<p>Als tijdelijke oplossing kan een universeel boekje handig zijn om nieuwe stempels te verzamelen bij erkende garages, naast een digitale history. Als langetermijnoplossing voor je onderhoudshistorie is een digitale tool beter, robuuster en overdraagbaar. GarageBook biedt dat gratis aan.</p>
<p>Lees ook <a href="/universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026">het beste alternatief voor een universeel onderhoudsboekje in 2026</a>, <a href="/digitaal-onderhoudsboekje">digitaal onderhoudsboekje</a>, <a href="/digitaal-onderhoudsboekje-auto">digitaal onderhoudsboekje auto</a> en <a href="/digitaal-onderhoudsboekje-motor">digitaal onderhoudsboekje motor</a>.</p>

<p><a href="/start" class="gb-button gb-button--primary">Start gratis met GarageBook</a></p>
HTML,
                'meta_title' => 'Universeel onderhoudsboekje – voordelen, nadelen en alternatieven | GarageBook',
                'meta_description' => 'Alles over het universele onderhoudsboekje: wat het is, wanneer je het gebruikt en waarom een digitaal alternatief zoals GarageBook beter werkt voor auto\'s en motoren.',
                'canonical_url' => null,
                'indexable' => true,
                'hero_image' => null,
                'structured_data' => [
                    [
                        'question' => 'Wat is een universeel onderhoudsboekje?',
                        'answer' => 'Een universeel onderhoudsboekje is een generiek, papieren boekje zonder koppeling aan een specifiek merk of voertuig. Het bevat stempelruimtes en invulvakken en wordt verkocht als vervanging of aanvulling op een origineel onderhoudsboekje.',
                    ],
                    [
                        'question' => 'Is een universeel onderhoudsboekje geaccepteerd bij dealers?',
                        'answer' => 'Erkende merkdealers accepteren een universeel boekje niet als officieel bewijs voor garantie of fabrikant-onderhoud. Voor eigen registratie is het bruikbaar, maar de waarde is beperkt ten opzichte van het originele boekje.',
                    ],
                    [
                        'question' => 'Wat is het beste alternatief voor een universeel onderhoudsboekje?',
                        'answer' => 'Een digitale onderhoudshistorie via GarageBook. Die bevat meer bewijs (foto\'s, facturen, beschrijvingen), gaat nooit verloren, is altijd beschikbaar en is overdraagbaar bij verkoop.',
                    ],
                    [
                        'question' => 'Kan ik een universeel onderhoudsboekje aanschaffen als mijn origineel kwijt is?',
                        'answer' => 'Ja, dat kan. Maar het lost de ontbrekende history van het verleden niet op. Een betere aanpak is het reconstrueren van de history via garages, facturen en foto\'s, en daarna overstappen op een digitale oplossing.',
                    ],
                    [
                        'question' => 'Hoeveel kost een universeel onderhoudsboekje?',
                        'answer' => 'Universele onderhoudsboekjes zijn te koop voor een paar euro bij autoparts-winkels en online. GarageBook als digitaal alternatief is volledig gratis te starten.',
                    ],
                ],
            ],
        ];
    }
};
