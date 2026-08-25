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
                ['kind'=>'mail_template','id'=>11,'label'=>'Absage','occurred_at'=>'2026-08-10 08:00:00'],
                ['kind'=>'mail_template_revision','id'=>12,'occurred_at'=>'2026-08-10 08:05:00'],
                ['kind'=>'mail_text_block','id'=>13,'label'=>'Rückfragen','occurred_at'=>'2026-08-10 08:10:00'],
                ['kind'=>'status_mail_rule','id'=>14,'action'=>'received → rejected','occurred_at'=>'2026-08-10 08:15:00'],
                ['kind'=>'mail_draft','id'=>15,'application_id'=>1,'status'=>'approved','occurred_at'=>'2026-08-10 08:20:00'],
            ];
        }
    }
    class TemporaryAdminAccessRepository { public array $items=[]; public function historyForUid(string $uid,int $limit):array{return array_slice(array_values(array_filter($this->items,static fn(array $item):bool=>in_array($uid,[$item['targetUid'],$item['grantedBy'],$item['revokedBy']],true))),0,$limit);} }
}
namespace {
    use OCA\FilzmannDataProtection\PublicApi\V1\DataSubjectRef; use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest; use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
    use OCA\Recruitment\Privacy\RecruitmentPersonalDataProvider; use OCA\Recruitment\Privacy\RecruitmentPrivacyProviderListener; use OCA\Recruitment\Repository\RecruitmentRepository; use OCA\Recruitment\Repository\TemporaryAdminAccessRepository;
    $adminAccess=new TemporaryAdminAccessRepository();$adminAccess->items=[['id'=>21,'targetUid'=>'self','grantedBy'=>'other-admin','startsAt'=>new DateTimeImmutable('2026-08-12T08:00:00+00:00'),'endsAt'=>new DateTimeImmutable('2026-08-12T12:00:00+00:00'),'revokedAt'=>null,'revokedBy'=>null]];
    $provider=new RecruitmentPersonalDataProvider(new RecruitmentRepository(),$adminAccess);
    $descriptor=$provider->descriptor();if($descriptor->appId()!=='adrecruitment'||$descriptor->contractVersion()!=='1.0'||!$descriptor->supportsSubjectType('nextcloud-user'))throw new RuntimeException('Recruitment-Provider beschreibt den Standalone-V1-Vertrag nicht korrekt.');
    $subject=new DataSubjectRef('nextcloud-user','self');
    $report=$provider->collect(new PersonalDataRequest($subject,'de','access-report',50,[]));
    $items=array_map(static fn($item)=>[
        'categoryId'=>$item->categoryId(),'categoryLabel'=>$item->categoryLabel(),'reference'=>$item->reference(),
        'summary'=>$item->summary(),'purpose'=>$item->purpose(),'source'=>$item->source(),
        'recipientCategories'=>$item->recipientCategories(),'retention'=>$item->retention(),
        'thirdCountryTransfer'=>$item->thirdCountryTransfer(),'automatedDecision'=>$item->automatedDecision(),
        'thirdPartyContentNotice'=>$item->thirdPartyContentNotice(),'attributes'=>$item->attributes(),
    ],$report->entries());
    $types=array_column($items,'categoryLabel');
    foreach(['Bewerbungszuständigkeit','Stellenverantwortung','Statusänderung','Interviewbearbeitung','Berechtigungsnachweis','BQ-Durchlauf','BQ-Bearbeitung','Posteingangsaktivität','Dokumentkommentar','Dokumentfeld-Verknüpfung','Mailvorlage','Mailvorlagenrevision','Mailtextblock','Statusmail-Regel','Statusmail-Entwurf','Zeitlich begrenzter Admin-Vollzugriff'] as $type)if(!in_array($type,$types,true))throw new RuntimeException('Recruitment-Datenklasse fehlt: '.$type);
    $encoded=json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    foreach(['01.08.26, 08:00 Uhr','09.08.26, 17:00 Uhr','Abgeschlossen','Bewerbungsnummer'] as $expected)if(!str_contains($encoded,$expected))throw new RuntimeException('Menschenlesbare Recruitment-Angabe fehlt: '.$expected);
    foreach(['given_name','family_name','email','phone','answers_json','body_text','selected_text','evaluation_note','original_recipient','delivery_recipient','private@example.invalid','Andere Person','other-admin'] as $forbidden)if(str_contains($encoded,$forbidden))throw new RuntimeException('Recruitment-Auskunft enthält Bewerber- oder technische Inhalte: '.$forbidden);
    if($report->status()!=='complete'||$items[0]['recipientCategories']===[])throw new RuntimeException('Recruitment-Vollständigkeit oder Verarbeitungsangaben fehlen.');
    $foreign=$provider->collect(new PersonalDataRequest(new DataSubjectRef('nextcloud-user','foreign'),'de','access-report',50,[]));if($foreign->status()!=='not_applicable'||$foreign->entries()!==[])throw new RuntimeException('Fremde interne Daten werden ausgegeben.');
    $unsupported=$provider->collect(new PersonalDataRequest(new DataSubjectRef('external-applicant','self'),'de','access-report',50,[]));if($unsupported->status()!=='not_applicable'||$unsupported->entries()!==[])throw new RuntimeException('Ein nicht unterstützter Subject-Typ erhält interne Recruitment-Daten.');
    if($provider->collect(new PersonalDataRequest($subject,'de','access-report',1,[]))->status()!=='partial')throw new RuntimeException('Ein begrenzter Recruitment-Bericht behauptet Vollständigkeit.');
    try{$provider->collect((new PersonalDataRequest($subject,'de','access-report',50,['adrecruitment'=>'opaque']))->forProvider('adrecruitment',50));throw new RuntimeException('Ein unbekannter Provider-Cursor wurde akzeptiert.');}catch(InvalidArgumentException){}
    $registry=new RegisterPersonalDataProvidersEvent();(new RecruitmentPrivacyProviderListener($provider))->handle($registry);if(array_keys($registry->providers())!==['adrecruitment'])throw new RuntimeException('Recruitment-Provider ist nicht registriert.');
    $bootstrap=file_get_contents(__DIR__.'/../lib/AppInfo/Application.php');if($bootstrap===false||!str_contains($bootstrap,'registerEventListener(RegisterPersonalDataProvidersEvent::class, RecruitmentPrivacyProviderListener::class)')||str_contains($bootstrap,'PersonalDataProviderRegistryEvent'))throw new RuntimeException('Recruitment-Provider fehlt am exklusiven Standalone-V1-Bootstrap.');
    echo "AD Recruitment privacy provider test passed\n";
}
