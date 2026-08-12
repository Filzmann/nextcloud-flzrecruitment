<?php
declare(strict_types=1);

namespace OCP\EventDispatcher { class Event { public function __construct() {} } interface IEventListener { public function handle(Event $event): void; } }
namespace OCA\Recruitment\AppInfo { final class Application { public const APP_ID='adrecruitment'; } }
namespace OCA\Recruitment\Repository {
    class RecruitmentRepository {
        public function personalDataForNextcloudUid(string $uid,int $limit):array {
            if($uid!=='self')return [];
            return [
                ['kind'=>'application_assignment','id'=>1,'application_id'=>1,'status'=>'received','occurred_at'=>'2026-08-01 08:00:00'],
                ['kind'=>'job_responsibility','id'=>2,'label'=>'Assistenz','occurred_at'=>'2026-08-01 09:00:00'],
                ['kind'=>'status_change','id'=>3,'application_id'=>1,'action'=>'received → interview','occurred_at'=>'2026-08-02 10:00:00'],
                ['kind'=>'interview','id'=>4,'application_id'=>1,'status'=>'completed','occurred_at'=>'2026-08-03 11:00:00'],
                ['kind'=>'permission_audit','id'=>5,'application_id'=>1,'action'=>'access_granted','role'=>'subject','occurred_at'=>'2026-08-04 12:00:00'],
                ['kind'=>'bq_run','id'=>6,'label'=>'BQ 08/26','occurred_at'=>'2026-08-05 13:00:00'],
                ['kind'=>'bq_assignment','id'=>7,'application_id'=>1,'action'=>'suitable','occurred_at'=>'2026-08-06 14:00:00'],
                ['kind'=>'message_audit','id'=>8,'action'=>'new → assigned','occurred_at'=>'2026-08-07 15:00:00'],
                ['kind'=>'document_comment','id'=>9,'action'=>'Kommentar gespeichert','occurred_at'=>'2026-08-08 16:00:00'],
                ['kind'=>'document_field_link','id'=>10,'action'=>'Feldnachweis gespeichert','occurred_at'=>'2026-08-09 17:00:00'],
            ];
        }
    }
}
namespace {
    require_once __DIR__.'/bootstrap.php';
    use OCA\LocalBase\Privacy\PersonalDataProviderRegistryEvent; use OCA\LocalBase\Privacy\PersonalDataRequest; use OCA\LocalBase\Privacy\PersonalDataSubject;
    use OCA\Recruitment\Privacy\RecruitmentPersonalDataProvider; use OCA\Recruitment\Privacy\RecruitmentPrivacyProviderListener; use OCA\Recruitment\Repository\RecruitmentRepository;
    $provider=new RecruitmentPersonalDataProvider(new RecruitmentRepository());
    $report=$provider->collect(new PersonalDataRequest(new PersonalDataSubject(PersonalDataSubject::NEXTCLOUD_USER,'self'),'de',PersonalDataRequest::PURPOSE_SELF_SERVICE,50));
    $items=array_map(static fn($item)=>$item->toArray(),$report->items());
    $types=array_column($items,'dataType');
    foreach(['Bewerbungszuständigkeit','Stellenverantwortung','Statusänderung','Interviewbearbeitung','Berechtigungsnachweis','BQ-Durchlauf','BQ-Bearbeitung','Posteingangsaktivität','Dokumentkommentar','Dokumentfeld-Verknüpfung'] as $type)if(!in_array($type,$types,true))throw new RuntimeException('Recruitment-Datenklasse fehlt: '.$type);
    $encoded=json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    foreach(['01.08.26, 08:00 Uhr','09.08.26, 17:00 Uhr','Abgeschlossen','Bewerbungsnummer'] as $expected)if(!str_contains($encoded,$expected))throw new RuntimeException('Menschenlesbare Recruitment-Angabe fehlt: '.$expected);
    foreach(['given_name','family_name','email','phone','answers_json','body_text','selected_text','evaluation_note','Andere Person'] as $forbidden)if(str_contains($encoded,$forbidden))throw new RuntimeException('Recruitment-Auskunft enthält Bewerber- oder technische Inhalte: '.$forbidden);
    if(!$report->isComplete()||$report->processing()->toArray()['categories']===[])throw new RuntimeException('Recruitment-Vollständigkeit oder Verarbeitungsangaben fehlen.');
    if((new RecruitmentPersonalDataProvider(new RecruitmentRepository()))->collect(new PersonalDataRequest(new PersonalDataSubject(PersonalDataSubject::NEXTCLOUD_USER,'foreign'),'de',PersonalDataRequest::PURPOSE_SELF_SERVICE,50))->items()!==[])throw new RuntimeException('Fremde interne Daten werden ausgegeben.');
    $registry=new PersonalDataProviderRegistryEvent();(new RecruitmentPrivacyProviderListener($provider))->handle($registry);if(array_keys($registry->providers())!==['adrecruitment'])throw new RuntimeException('Recruitment-Provider ist nicht registriert.');
    $bootstrap=file_get_contents(__DIR__.'/../lib/AppInfo/Application.php');if($bootstrap===false||!str_contains($bootstrap,'RecruitmentPrivacyProviderListener::class'))throw new RuntimeException('Recruitment-Provider fehlt im Nextcloud-Bootstrap.');
    echo "AD Recruitment privacy provider test passed\n";
}
