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
                'name' => 'Geen onderhoudslog na 3 dagen',
                'subject' => 'Je voertuig staat al klaar in GarageBook',
                'body' => <<<'BODY'
Je voertuig staat al in GarageBook. De volgende stap is simpel: voeg je eerste onderhoudsregistratie toe.

Zelfs een enkele registratie maakt je onderhoudsgeschiedenis direct bruikbaarder en waardevoller.
BODY,
                'cta_text' => 'Voeg onderhoud toe',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
                'name' => 'Geen onderhoudslog na 14 dagen',
                'subject' => 'Hoe compleet is jouw onderhoudsgeschiedenis?',
                'body' => <<<'BODY'
Je voertuig staat erin, maar zonder onderhoudsregistraties blijft je dossier onvolledig.

Voeg je laatste beurt, reparatie of bandenwissel toe en begin met een historie waar je later echt iets aan hebt.
BODY,
                'cta_text' => 'Start je onderhoudshistorie',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
                'name' => 'Geen onderhoudslog na 30 dagen',
                'subject' => 'Een voertuig zonder historie vertelt maar de helft van het verhaal',
                'body' => <<<'BODY'
Je voertuig staat in GarageBook, maar de echte waarde zit in de onderhoudsgeschiedenis.

Leg je eerste onderhoud vast en bouw aan een dossier dat overzicht, vertrouwen en context geeft.
BODY,
                'cta_text' => 'Voeg je eerste onderhoud toe',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
                'name' => 'Na eerste onderhoudslog',
                'subject' => 'Je onderhoudshistorie groeit',
                'body' => <<<'BODY'
Je eerste onderhoud staat erin. Dat is het begin van een dossier dat met elke registratie waardevoller wordt.

Vul je historie verder aan met eerdere of recente onderhoudsmomenten zodat je overzicht compleet blijft.
BODY,
                'cta_text' => 'Onderhoud aanvullen',
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
