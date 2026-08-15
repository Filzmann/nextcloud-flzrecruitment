<?php
declare(strict_types=1);

namespace OCA\Recruitment\Privacy;

use DateTimeImmutable; use DateTimeInterface;
use OCA\LocalBase\Privacy\PersonalDataItem; use OCA\LocalBase\Privacy\PersonalDataProcessingInfo; use OCA\LocalBase\Privacy\PersonalDataProvider; use OCA\LocalBase\Privacy\PersonalDataReport; use OCA\LocalBase\Privacy\PersonalDataRequest; use OCA\LocalBase\Privacy\PersonalDataSubject;
use OCA\Recruitment\AppInfo\Application; use OCA\Recruitment\Repository\RecruitmentRepository;

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
    public function __construct(private RecruitmentRepository $repository){}
    public function appId():string{return Application::APP_ID;}
    public function supportedSubjectTypes():array{return [PersonalDataSubject::NEXTCLOUD_USER];}
    public function collect(PersonalDataRequest $request):PersonalDataReport{
        $rows=$this->repository->personalDataForNextcloudUid($request->subject()->id(),$request->limit());$complete=count($rows)<$request->limit();$items=[];
        foreach(array_slice($rows,0,$request->limit()) as $row)$items[]=$this->item($row);
        return new PersonalDataReport($items,new PersonalDataProcessingInfo(
            purposes:['Durchführung und Nachvollziehbarkeit des Recruitingprozesses','Steuerung fachlicher Zuständigkeiten und Berechtigungen'],
            categories:['Nextcloud-Kennung in Zuständigkeiten und Bearbeitungsnachweisen','Zeitpunkt und Art interner Recruitingaktivitäten'],
            recipients:['Berechtigte Recruiting-Mitarbeiter*innen im jeweiligen Scope','Berechtigte Lohn- oder Erstbegleitungsrollen für ausdrücklich freigegebene Teilbereiche','Nextcloud-Administrator*innen mit Verwaltungsrechten'],
            source:'Eigene interne Bearbeitungshandlungen sowie fachliche Zuordnungen durch berechtigte Personen',
            retentionCriteria:self::RETENTION,
            thirdCountryTransfers:'Durch AD Recruitment sind keine Drittlandübermittlungen vorgesehen.',
            automatedDecisionMaking:'AD Recruitment trifft keine ausschließlich automatisierte Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.'
        ),$complete,$complete?[]:['Ausgabelimit erreicht; weitere interne Recruitment-Bezüge können vorhanden sein.'],'AD Recruitment');
    }
    private function item(array $row):PersonalDataItem{
        [$type,$purpose]=self::TYPES[(string)$row['kind']]??['Recruitment-Aktivität','Nachvollziehbarkeit einer internen Recruitingaktivität'];
        $attributes=['Gespeichert am'=>self::dateTime($row['occurred_at']??'')];
        if(isset($row['application_id']))$attributes['Bewerbungsnummer']=(string)$row['application_id'];
        if(isset($row['label']))$attributes['Bezeichnung']=(string)$row['label'];
        if(isset($row['status']))$attributes['Status']=self::translate((string)$row['status']);
        if(isset($row['action']))$attributes['Vorgang']=self::translate((string)$row['action']);
        if(isset($row['role']))$attributes['Bezug']=$row['role']==='subject'?'Deine Kennung war Gegenstand des Nachweises':'Du hast den Vorgang ausgeführt';
        return new PersonalDataItem((string)$row['kind'],$type.' vom '.self::dateTime($row['occurred_at']??''),'recruitment-'.(string)$row['kind'].':'.(string)$row['id'],$attributes,$purpose,self::RETENTION,'Folgende internen Recruitment-Bezüge sind mit deiner Kennung gespeichert:','Inhalte der Bewerbungsakte und Identitäten anderer Personen werden in diesem internen Nutzerbericht nicht ausgegeben.',$type);
    }
    private static function translate(string $value):string{return ['received'=>'Eingegangen','completed'=>'Abgeschlossen','approved'=>'Genehmigt','suitable'=>'Geeignet','access_granted'=>'Zugriff erteilt'][$value]??str_replace(['_','→'],[' ',' → '],$value);}
    private static function dateTime(mixed $value):string{if($value instanceof DateTimeInterface)return $value->format('d.m.y, H:i').' Uhr';try{return(new DateTimeImmutable((string)$value))->format('d.m.y, H:i').' Uhr';}catch(\Throwable){return(string)$value;}}
}
