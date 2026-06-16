<?php

namespace Database\Seeders;

use App\Models\LifecycleEmailTemplate;
use Illuminate\Database\Seeder;

class LifecycleEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'email_key' => LifecycleEmailTemplate::NO_VEHICLE_ADDED,
                'name' => 'Nog geen voertuig toegevoegd',
                'subject' => 'Voeg je eerste voertuig toe aan GarageBook',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je garage staat nog leeg in GarageBook. Zodra je je eerste voertuig toevoegt, heb je meteen een centrale plek voor onderhoud, documenten en later ook je volledige historie.

Begin klein: voeg vandaag je eerste voertuig toe en bouw van daaruit verder.
BODY,
                'cta_text' => 'Voertuig toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
                'name' => 'Geen onderhoudslog - dag 3',
                'subject' => 'Je eerste onderhoudsnotitie staat klaar',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je voertuig staat al in GarageBook. De volgende logische stap is het eerste onderhoud toevoegen, zodat je geschiedenis direct gaat leven.

Dat hoeft niet volledig te zijn. Een oliebeurt, bandenwissel, reparatie of factuur is al genoeg om je dossier te starten.
BODY,
                'cta_text' => 'Eerste onderhoud toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
                'name' => 'Geen onderhoudslog - dag 14',
                'subject' => 'Bouw je onderhoudshistorie stap voor stap',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Met één onderhoudsregel wordt je voertuiggeschiedenis al bruikbaarder. Je ziet sneller wat er is gedaan, wanneer het gebeurde en wat je later nog wilt terugvinden.

Voeg vandaag je eerste onderhoud toe en vul daarna rustig verder aan.
BODY,
                'cta_text' => 'Start je onderhoudshistorie',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
                'name' => 'Geen onderhoudslog - dag 30',
                'subject' => 'Maak van je voertuig een compleet dossier',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je voertuig staat in GarageBook, maar zonder onderhoudsregels blijft het verhaal nog half leeg.

Juist onderhoud maakt je dossier waardevol: wat is gedaan, wanneer, bij welke kilometerstand en met welke onderdelen of facturen.

Begin met de laatste beurt die je nog weet en werk vanaf daar verder.
BODY,
                'cta_text' => 'Voeg je eerste onderhoud toe',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
                'name' => 'Na eerste onderhoudslog',
                'subject' => 'Je onderhoudshistorie is gestart',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Mooi, je eerste onderhoudsregel staat nu in GarageBook.

Vanaf hier kun je je onderhoudshistorie verder aanvullen met eerdere beurten, facturen of andere documenten. Zo wordt je tijdlijn steeds completer.
BODY,
                'cta_text' => 'Onderhoudshistorie bekijken',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::INACTIVE_USER_RETURN,
                'name' => 'Lang inactief',
                'subject' => 'Je garage staat klaar als je verder wilt gaan',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Het is alweer even geleden dat je in GarageBook was. Je voertuigen, onderhoud en documenten wachten hier nog steeds op je.

Open je garage weer en pak eenvoudig verder waar je gebleven was.
BODY,
                'cta_text' => 'Ga verder met je garage',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            LifecycleEmailTemplate::query()->updateOrCreate(
                ['email_key' => $template['email_key']],
                $template,
            );
        }
    }
}
