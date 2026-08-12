<?php
declare(strict_types=1);
namespace OCA\Recruitment\Privacy;
use OCA\LocalBase\Privacy\PersonalDataProviderRegistryEvent; use OCP\EventDispatcher\Event; use OCP\EventDispatcher\IEventListener;
/** @template-implements IEventListener<PersonalDataProviderRegistryEvent> */
final class RecruitmentPrivacyProviderListener implements IEventListener{public function __construct(private RecruitmentPersonalDataProvider $provider){}public function handle(Event $event):void{if($event instanceof PersonalDataProviderRegistryEvent)$event->register($this->provider);}}
