<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class JmvInventoryNotification extends Notification {use Queueable;public function __construct(private readonly string $subject,private readonly string $message,private readonly string $url){}public function via(object $notifiable):array{return['database','mail'];}public function toMail(object $notifiable):MailMessage{return(new MailMessage)->subject($this->subject)->greeting('Hello '.$notifiable->name.',')->line($this->message)->action('Open JMV Inventory',$this->url);}public function toArray(object $notifiable):array{return['title'=>$this->subject,'message'=>$this->message,'url'=>$this->url,'module'=>'jmv_inventory'];}}
