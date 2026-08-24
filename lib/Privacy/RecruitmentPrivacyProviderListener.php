<?php
declare(strict_types=1);
namespace OCA\Recruitment\Privacy;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent; use OCP\EventDispatcher\Event; use OCP\EventDispatcher\IEventListener;
/** @template-implements IEventListener<RegisterPersonalDataProvidersEvent> */
final class RecruitmentPrivacyProviderListener implements IEventListener{public function __construct(private RecruitmentPersonalDataProvider $provider){}public function handle(Event $event):void{if($event instanceof RegisterPersonalDataProvidersEvent)$event->register($this->provider);}}
