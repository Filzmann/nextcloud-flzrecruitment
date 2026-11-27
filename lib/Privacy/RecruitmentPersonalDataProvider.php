<?php
declare(strict_types=1);

namespace OCA\FlzRecruitment\Privacy;

use DateTimeImmutable; use DateTimeInterface; use InvalidArgumentException;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataEntry; use OCA\FlzDataProtection\PublicApi\V1\PersonalDataPage; use OCA\FlzDataProtection\PublicApi\V1\PersonalDataProvider; use OCA\FlzDataProtection\PublicApi\V1\PersonalDataRequest; use OCA\FlzDataProtection\PublicApi\V1\ProviderDescriptor;
use OCA\FlzRecruitment\AppInfo\Application; use OCA\FlzRecruitment\Repository\RecruitmentRepository; use OCA\FlzRecruitment\Repository\TemporaryAdminAccessRepository;
use OCP\IL10N;

final class RecruitmentPersonalDataProvider implements PersonalDataProvider {
    private const RETENTION='Nach dem derzeit gespeicherten Retention-Status der Bewerbung; eine ausführende Löschfrist ist noch nicht festgelegt.';
    private function types(): array {
        return [
        'application_assignment'=>[$this->t('Bewerbungszuständigkeit'),$this->t('Bearbeitung einer dir zugewiesenen Bewerbung')],
        'job_responsibility'=>['Stellenverantwortung',$this->t('Zuordnung als verantwortliche Person für eine Stelle')],
        'status_change'=>[$this->t('Statusänderung'),$this->t('Nachvollziehbarkeit einer von dir ausgeführten Statusänderung')],
        'interview'=>['Interviewbearbeitung',$this->t('Nachvollziehbarkeit eines von dir bearbeiteten Interviews')],
        'permission_audit'=>['Berechtigungsnachweis',$this->t('Nachweis einer Berechtigungsentscheidung mit Bezug zu deiner Kennung')],
        'bq_run'=>['BQ-Durchlauf',$this->t('Nachvollziehbarkeit eines von dir verwalteten BQ-Durchlaufs')],
        'bq_assignment'=>['BQ-Bearbeitung',$this->t('Nachvollziehbarkeit einer von dir vorgenommenen BQ-Bearbeitung')],
        'message_audit'=>[$this->t('Posteingangsaktivität'),$this->t('Nachvollziehbarkeit einer von dir ausgeführten Posteingangsaktion')],
        'document_comment'=>['Dokumentkommentar',$this->t('Nachweis eines von dir gespeicherten Dokumentkommentars')],
        'document_field_link'=>[$this->t('Dokumentfeld-Verknüpfung'),$this->t('Nachweis einer von dir gespeicherten Dokumentfeld-Verknüpfung')],
        'mail_template'=>['Mailvorlage',$this->t('Nachvollziehbarkeit einer von dir verwalteten Mailvorlage')],
        'mail_template_revision'=>['Mailvorlagenrevision',$this->t('Nachvollziehbarkeit einer von dir gespeicherten Vorlagenrevision')],
        'mail_text_block'=>['Mailtextblock',$this->t('Nachvollziehbarkeit eines von dir verwalteten Mailtextblocks')],
        'status_mail_rule'=>['Statusmail-Regel',$this->t('Nachvollziehbarkeit einer von dir verwalteten Statusmail-Regel')],
        'mail_draft'=>['Statusmail-Entwurf',$this->t('Nachvollziehbarkeit eines von dir erstellten oder freigegebenen Mailentwurfs')],
        ];
    }
    public function __construct(private RecruitmentRepository $repository,private TemporaryAdminAccessRepository $adminAccess, private ?IL10N $l10n = null){}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return isset($this->l10n) ? $this->l10n->t($text) : $text;
    }
    public function descriptor():ProviderDescriptor{return new ProviderDescriptor(Application::APP_ID,$this->t('Filzmann Recruitment'),'1.0',['nextcloud-user'],['personal-data'],500);}
    public function collect(PersonalDataRequest $request):PersonalDataPage{
        if($request->subject()->subjectType()!=='nextcloud-user')return new PersonalDataPage('not_applicable');
        if($request->cursor()!==null)throw new InvalidArgumentException('Filzmann Recruitment unterstützt kein Cursor-Paging.');
        $limit=$request->pageLimit();$subjectUid=$request->subject()->subjectId();$rows=$this->repository->personalDataForNextcloudUid($subjectUid,$limit+1);$adminHistory=$this->adminAccess->historyForUid($subjectUid,$limit+1);$complete=count($rows)+count($adminHistory)<=$limit;$items=[];
        foreach(array_slice($rows,0,$limit) as $row)$items[]=$this->item($row);
        foreach($adminHistory as $grant){if(count($items)>=$limit)break;$items[]=$this->adminItem($subjectUid,$grant);}
        if($items===[])return new PersonalDataPage('not_applicable');
        return new PersonalDataPage($complete?'complete':'partial',$items,$complete?[]:[$this->t('Ausgabelimit erreicht; weitere interne Recruitment-Bezüge können vorhanden sein.')]);
    }
    private function adminItem(string $uid,array $grant):PersonalDataEntry{$roles=[];if($grant['targetUid']===$uid)$roles[]=$this->t('Ziel der Vollzugriffsfreigabe');if($grant['grantedBy']===$uid)$roles[]=$this->t('Freigebende berechtigte Person');if($grant['revokedBy']===$uid)$roles[]=$this->t('Widerrufende berechtigte Person');$actualEnd=$grant['revokedAt']??$grant['endsAt'];return new PersonalDataEntry('admin-access',$this->t('Zeitlich begrenzter Admin-Vollzugriff'),'admin-access:'.$grant['id'],$this->t('Admin-Vollzugriff vom ').$this->dateTime($grant['startsAt']),$this->t('Nachweis einer zeitlich begrenzten administrativen Recruitment-Freigabe'),$this->t('App-lokale Freigabesteuerung in Filzmann Recruitment'),[$this->t('Betroffene Person und ausdrücklich berechtigte Datenschutz-Prüfrolle')],self::RETENTION,$this->t('Durch Filzmann Recruitment sind keine Drittlandübermittlungen vorgesehen.'),$this->t('Der Server beendet den Vollzugriff spätestens nach 24 Stunden automatisch.'),$this->t('Kennungen anderer beteiligter Personen werden nicht ausgegeben.'),[$this->t('Eigene Rolle im Vorgang')=>implode(', ',$roles),'Beginn'=>$this->dateTime($grant['startsAt']),$this->t('Geplantes Ende')=>$this->dateTime($grant['endsAt']),$this->t('Tatsächliches Ende')=>$this->dateTime($actualEnd),$this->t('Status')=>$grant['revokedAt']===null?$this->t('planmäßig beendet oder noch aktiv'):'widerrufen']);}
    private function item(array $row):PersonalDataEntry{
        [$type,$purpose]=$this->types()[(string)$row['kind']]??[$this->t('Recruitment-Aktivität'),$this->t('Nachvollziehbarkeit einer internen Recruitingaktivität')];
        $attributes=[$this->t('Gespeichert am')=>$this->dateTime($row['occurred_at']??'')];
        if(isset($row['application_id']))$attributes['Bewerbungsnummer']=(string)$row['application_id'];
        if(isset($row['label']))$attributes['Bezeichnung']=(string)$row['label'];
        if(isset($row['status']))$attributes[$this->t('Status')]=$this->translate((string)$row['status']);
        if(isset($row['action']))$attributes['Vorgang']=$this->translate((string)$row['action']);
        if(isset($row['role']))$attributes['Bezug']=$row['role']==='subject'?$this->t('Deine Kennung war Gegenstand des Nachweises'):$this->t('Du hast den Vorgang ausgeführt');
        return new PersonalDataEntry(
            (string)$row['kind'],$type,'recruitment-'.(string)$row['kind'].':'.(string)$row['id'],$type.' vom '.$this->dateTime($row['occurred_at']??''),$purpose,
            $this->t('Eigene interne Bearbeitungshandlungen sowie fachliche Zuordnungen durch berechtigte Personen'),
            ['Berechtigte Recruiting-Mitarbeiter*innen im jeweiligen Scope','Berechtigte Lohn- oder Erstbegleitungsrollen für ausdrücklich freigegebene Teilbereiche','Nextcloud-Administrator*innen mit Verwaltungsrechten'],
            self::RETENTION,$this->t('Durch Filzmann Recruitment sind keine Drittlandübermittlungen vorgesehen.'),$this->t('Filzmann Recruitment trifft keine ausschließlich automatisierte Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.'),
            $this->t('Inhalte der Bewerbungsakte und Identitäten anderer Personen werden in diesem internen Nutzerbericht nicht ausgegeben.'),$attributes
        );
    }
    private function translate(string $value):string{return ['received'=>$this->t('Eingegangen'),'completed'=>$this->t('Abgeschlossen'),'approved'=>'Genehmigt','suitable'=>'Geeignet','access_granted'=>$this->t('Zugriff erteilt')][$value]??str_replace(['_','→'],[' ',' → '],$value);}
    private function dateTime(mixed $value):string{if($value instanceof DateTimeInterface)return $value->format('d.m.y, H:i').$this->t(' Uhr');try{return(new DateTimeImmutable((string)$value))->format('d.m.y, H:i').$this->t(' Uhr');}catch(\Throwable){return(string)$value;}}
}
