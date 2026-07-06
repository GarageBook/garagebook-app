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
                'email_key' => LifecycleEmailTemplate::NO_VEHICLE_DAY2,
                'name' => 'Geen voertuig - dag 2',
                'subject' => 'Voeg je eerste voertuig toe',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je GarageBook begint met één voertuig. Voeg je voertuig toe, dan kun je daarna direct onderhoud vastleggen.
BODY,
                'cta_text' => 'Voertuig toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_VEHICLE_ADDED,
                'name' => 'Nog geen voertuig toegevoegd',
                'subject' => 'Start je GarageBook met 1 voertuig',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je garage is nog leeg. Voeg je voertuig toe en leg daarna je eerste onderhoud vast.
BODY,
                'cta_text' => 'Voertuig toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
                'name' => 'Geen onderhoudslog - dag 3',
                'subject' => 'Leg je eerste onderhoud vast',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je voertuig staat in GarageBook. Voeg nu de laatste beurt, reparatie of controle toe, dan is je onderhoudshistorie gestart.
BODY,
                'cta_text' => 'Onderhoud toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
                'name' => 'Geen onderhoudslog - dag 14',
                'subject' => 'Je onderhoudshistorie mist nog de eerste regel',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Met één onderhoudsregel wordt je GarageBook al bruikbaar. Begin met de laatste beurt die je nog weet.
BODY,
                'cta_text' => 'Eerste onderhoud toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
                'name' => 'Geen onderhoudslog - dag 30',
                'subject' => 'Maak je voertuiggeschiedenis compleet',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je voertuig staat klaar, maar de onderhoudshistorie ontbreekt nog. Voeg één recente beurt toe en vul later rustig aan.
BODY,
                'cta_text' => 'Laatste onderhoud toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
                'name' => 'Na eerste onderhoudslog',
                'subject' => 'Je eerste onderhoud staat erin. Voeg nu bewijs toe',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je onderhoudshistorie is gestart. Maak deze regel sterker met een factuur, foto of extra notitie.
BODY,
                'cta_text' => 'Historie aanvullen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::UPLOAD_DOCUMENT,
                'name' => 'Document toevoegen',
                'subject' => 'Voeg bewijs toe aan je onderhoud',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Je onderhoud staat erin. Voeg een factuur, keuringsrapport of foto toe, zodat je historie later beter te onderbouwen is.
BODY,
                'cta_text' => 'Document toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::VEHICLE_PHOTO_REMINDER,
                'name' => 'Voertuigfoto toevoegen',
                'subject' => 'Maak je GarageBook herkenbaarder',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Geef je voertuig een foto. Dan herken je je GarageBook sneller en voelt je digitale onderhoudshistorie completer.
BODY,
                'cta_text' => 'Foto toevoegen',
                'is_active' => true,
            ],
            [
                'email_key' => LifecycleEmailTemplate::INACTIVE_USER_RETURN,
                'name' => 'Lang inactief',
                'subject' => 'Is je GarageBook nog up-to-date?',
                'body' => <<<'BODY'
Hoi {{ first_name }},

Is er sinds je laatste bezoek onderhoud gedaan? Werk je GarageBook bij met de nieuwste beurt of controle.
BODY,
                'cta_text' => 'GarageBook bijwerken',
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
