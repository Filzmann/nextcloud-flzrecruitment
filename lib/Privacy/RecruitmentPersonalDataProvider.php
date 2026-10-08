<?php
declare(strict_types=1);

namespace OCA\FlzRecruitment\Privacy;

use DateTimeImmutable; use DateTimeInterface; use InvalidArgumentException;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataEntry; use OCA\FlzDataProtection\PublicApi\V1\PersonalDataPage; use OCA\FlzDataProtection\PublicApi\V1\PersonalDataProvider; use OCA\FlzDataProtection\PublicApi\V1\PersonalDataRequest; use OCA\FlzDataProtection\PublicApi\V1\ProviderDescriptor;
use OCA\FlzRecruitment\AppInfo\Application; use OCA\FlzRecruitment\Repository\RecruitmentRepository; use OCA\FlzRecruitment\Repository\TemporaryAdminAccessRepository;

final class RecruitmentPersonalDataProvider implements PersonalDataProvider {
    private const RETENTION='Nach dem derzeit gespeicherten Retention-Status der Bewerbung; eine ausführende Löschfrist ist noch nicht festgelegt.';
    private const TYPES=[
        'application_assignment'=>['Bewerbungszuständigkeit','Bearbeitung einer dir zugewiesenen Bewerbung'],
        'job_responsibility'=>['Stellenverantwortung','Zuordnung als verantwortliche Person für eine Stelle'],
        'status_change'=>['Statusänderung','Nachvollziehbarkeit einer von dir ausgeführten Statusänderung'],
        'interview'=>['Interviewbearbeitung','Nachvollziehbarkeit eines von dir bearbeiteten Interviews'],
        'permission_audit'=>['Berechtigungsnachweis','Nachweis einer Berechtigungsentscheidung mit Bezug zu deiner Kennung'],
        'bq_run'=>['BQ-Durchlauf','Nachvollziehbarkeit eines von dir verwalteten BQ-Durchlaufs'],
        'bq_assignment'=>['BQ-Bearbeitung','Nachvollziehbarkeit einer von dir vorgenommenen BQ-Bearbeitung'],
        'message_audit'=>['Posteingangsaktivität','Nachvollziehbarkeit einer von dir ausgeführten Posteingangsaktion'],
        'document_comment'=>['Dokumentkommentar','Nachweis eines von dir gespeicherten Dokumentkommentars'],
        'document_field_link'=>['Dokumentfeld-Verknüpfung','Nachweis einer von dir gespeicherten Dokumentfeld-Verknüpfung'],
        'mail_template'=>['Mailvorlage','Nachvollziehbarkeit einer von dir verwalteten Mailvorlage'],
        'mail_template_revision'=>['Mailvorlagenrevision','Nachvollziehbarkeit einer von dir gespeicherten Vorlagenrevision'],
        'mail_text_block'=>['Mailtextblock','Nachvollziehbarkeit eines von dir verwalteten Mailtextblocks'],
        'status_mail_rule'=>['Statusmail-Regel','Nachvollziehbarkeit einer von dir verwalteten Statusmail-Regel'],
        'mail_draft'=>['Statusmail-Entwurf','Nachvollziehbarkeit eines von dir erstellten oder freigegebenen Mailentwurfs'],
    ];
    public function __construct(private RecruitmentRepository $repository,private TemporaryAdminAccessRepository $adminAccess){}
    public function descriptor():ProviderDescriptor{return new ProviderDescriptor(Application::APP_ID,'Filzmann Recruitment','1.0',['nextcloud-user'],['personal-data'],500);}
    public function collect(PersonalDataRequest $request):PersonalDataPage{
        if($request->subject()->subjectType()!=='nextcloud-user')return new PersonalDataPage('not_applicable');
        if($request->cursor()!==null)throw new InvalidArgumentException('Filzmann Recruitment does not support cursor paging.');
        $limit=$request->pageLimit();$subjectUid=$request->subject()->subjectId();$rows=$this->repository->personalDataForNextcloudUid($subjectUid,$limit+1);$adminHistory=$this->adminAccess->historyForUid($subjectUid,$limit+1);$complete=count($rows)+count($adminHistory)<=$limit;$items=[];
        foreach(array_slice($rows,0,$limit) as $row)$items[]=$this->item($row);
        foreach($adminHistory as $grant){if(count($items)>=$limit)break;$items[]=$this->adminItem($subjectUid,$grant);}
        if($items===[])return new PersonalDataPage('not_applicable');
        return new PersonalDataPage($complete?'complete':'partial',$items,$complete?[]:['Ausgabelimit erreicht; weitere interne Recruitment-Bezüge können vorhanden sein.']);
    }
    private function adminItem(string $uid,array $grant):PersonalDataEntry{$roles=[];if($grant['targetUid']===$uid)$roles[]='Ziel der Vollzugriffsfreigabe';if($grant['grantedBy']===$uid)$roles[]='Freigebendes Mitglied von Datenschutzbeauftragte';if($grant['revokedBy']===$uid)$roles[]='Widerrufendes Mitglied von Datenschutzbeauftragte';$actualEnd=$grant['revokedAt']??$grant['endsAt'];return new PersonalDataEntry('admin-access','Zeitlich begrenzter Admin-Vollzugriff','admin-access:'.$grant['id'],'Admin-Vollzugriff vom '.self::dateTime($grant['startsAt']),'Nachweis einer zeitlich begrenzten administrativen Recruitment-Freigabe','App-lokale Freigabesteuerung in Filzmann Recruitment',['Betroffene Person und ausdrücklich berechtigte Datenschutz-Prüfrolle'],self::RETENTION,'Durch Filzmann Recruitment sind keine Drittlandübermittlungen vorgesehen.','Der Server beendet den Vollzugriff spätestens nach 24 Stunden automatisch.','Kennungen anderer beteiligter Personen werden nicht ausgegeben.',['Eigene Rolle im Vorgang'=>implode(', ',$roles),'Beginn'=>self::dateTime($grant['startsAt']),'Geplantes Ende'=>self::dateTime($grant['endsAt']),'Tatsächliches Ende'=>self::dateTime($actualEnd),'Status'=>$grant['revokedAt']===null?'planmäßig beendet oder noch aktiv':'widerrufen']);}
    private function item(array $row):PersonalDataEntry{
        [$type,$purpose]=self::TYPES[(string)$row['kind']]??['Recruitment-Aktivität','Nachvollziehbarkeit einer internen Recruitingaktivität'];
        $attributes=['Gespeichert am'=>self::dateTime($row['occurred_at']??'')];
        if(isset($row['application_id']))$attributes['Bewerbungsnummer']=(string)$row['application_id'];
        if(isset($row['label']))$attributes['Bezeichnung']=(string)$row['label'];
        if(isset($row['status']))$attributes['Status']=self::translate((string)$row['status']);
        if(isset($row['action']))$attributes['Vorgang']=self::translate((string)$row['action']);
        if(isset($row['role']))$attributes['Bezug']=$row['role']==='subject'?'Deine Kennung war Gegenstand des Nachweises':'Du hast den Vorgang ausgeführt';
        return new PersonalDataEntry(
            (string)$row['kind'],$type,'recruitment-'.(string)$row['kind'].':'.(string)$row['id'],$type.' vom '.self::dateTime($row['occurred_at']??''),$purpose,
            'Eigene interne Bearbeitungshandlungen sowie fachliche Zuordnungen durch berechtigte Personen',
            ['Berechtigte Recruiting-Mitarbeiter*innen im jeweiligen Scope','Berechtigte Lohn- oder Erstbegleitungsrollen für ausdrücklich freigegebene Teilbereiche','Nextcloud-Administrator*innen mit Verwaltungsrechten'],
            self::RETENTION,'Durch Filzmann Recruitment sind keine Drittlandübermittlungen vorgesehen.','Filzmann Recruitment trifft keine ausschließlich automatisierte Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.',
            'Inhalte der Bewerbungsakte und Identitäten anderer Personen werden in diesem internen Nutzerbericht nicht ausgegeben.',$attributes
        );
    }
    private static function translate(string $value):string{return ['received'=>'Eingegangen','completed'=>'Abgeschlossen','approved'=>'Genehmigt','suitable'=>'Geeignet','access_granted'=>'Zugriff erteilt'][$value]??str_replace(['_','→'],[' ',' → '],$value);}
    private static function dateTime(mixed $value):string{if($value instanceof DateTimeInterface)return $value->format('d.m.y, H:i').' Uhr';try{return(new DateTimeImmutable((string)$value))->format('d.m.y, H:i').' Uhr';}catch(\Throwable){return(string)$value;}}
}
