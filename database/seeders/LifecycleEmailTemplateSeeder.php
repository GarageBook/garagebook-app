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
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
                'name' => 'Geen onderhoudslog - dag 3',
                'subject' => 'Je voertuig staat al klaar in GarageBook',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je hebt je voertuig al toegevoegd aan GarageBook. Mooi begin.

De volgende stap is simpel: voeg je laatste onderhoudsbeurt toe. Dat hoeft niet perfect of compleet te zijn. Een oliebeurt, bandenwissel, kettingonderhoud, reparatie of factuur is al genoeg om je onderhoudshistorie op gang te brengen.

Zo bouw je stap voor stap een overzichtelijk dossier op dat later waardevol kan zijn voor jezelf en bij verkoop.
BODY,
                'cta_text' => 'Voeg onderhoud toe',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
                'name' => 'Geen onderhoudslog - dag 14',
                'subject' => 'Hoe compleet is jouw onderhoudsgeschiedenis?',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Onderhoud is makkelijk te vergeten. Bonnetjes verdwijnen, kilometerstanden raken verspreid en na een tijdje weet je niet meer precies wat wanneer is gedaan.

Met GarageBook hoef je niet alles in een keer compleet te maken. Begin gewoon met je meest recente onderhoudsbeurt. Een registratie is genoeg om structuur aan te brengen.

Voeg vandaag je eerste onderhoud toe en bouw je voertuiggeschiedenis vanaf daar verder op.
BODY,
                'cta_text' => 'Start je onderhoudshistorie',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
                'name' => 'Geen onderhoudslog - dag 30',
                'subject' => 'Een voertuig zonder historie vertelt maar de helft van het verhaal',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je voertuig staat in GarageBook, maar zonder onderhoudsregels blijft je historie nog leeg.

Juist onderhoud vertelt het verhaal van een voertuig: wat is gedaan, wanneer, bij welke kilometerstand en met welke onderdelen of facturen.

Je hoeft niet terug tot dag een. Begin met de laatste beurt die je nog weet. Dan heb je vanaf nu een centrale plek voor alles wat je later niet kwijt wilt raken.
BODY,
                'cta_text' => 'Voeg je eerste onderhoud toe',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
                'name' => 'Na eerste onderhoudslog',
                'subject' => 'Je onderhoudshistorie groeit',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Mooi, je eerste onderhoudsregel staat in GarageBook.

Daarmee is je voertuiggeschiedenis begonnen. Je kunt hem nu verder aanvullen met eerdere beurten, facturen, foto's of reparaties die je nog hebt liggen.

Elke toevoeging maakt je dossier completer en waardevoller.
BODY,
                'cta_text' => 'Onderhoud aanvullen',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            LifecycleEmailTemplate::query()->firstOrCreate(
                ['email_key' => $template['email_key']],
                $template,
            );
        }
    }
}
