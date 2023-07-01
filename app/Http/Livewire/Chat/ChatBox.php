<?php

namespace App\Http\Livewire\Chat;

use App\Models\Message;
use App\Notifications\MessageRead;
use App\Notifications\MessageSent;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ChatBox extends Component
{
    public $selectedConversation;
    public $body;
    public $loadedMessages;
    public $paginate_var=10;
    
    public function loadMore(): void {
        // dd('detected');
        $this->paginate_var += 10 ;
        $this->loadMessages();
        $this->dispatchBrowserEvent('update-chat-height');
    }
    protected $listeners=[
        'loadMore'
    ];
    public function getListeners(){
        $auth_id= auth()->user()->id;
        return[
            'loadMore',
            "echo-private:users.{$auth_id},.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated"=>'broadcastedNotifications'
        ];
    }


    public function broadcastedNotifications($event)
    {
       

        if ($event['type'] == MessageSent::class) {

            if ($event['conversation_id'] == $this->selectedConversation->id) {

                $this->dispatchBrowserEvent('scroll-bottom');

                $newMessage = Message::find($event['message_id']);


                #push message
                $this->loadedMessages->push($newMessage);


                #mark as read
                $newMessage->read_at = now();
                $newMessage->save();

                #broadcast 
                $this->selectedConversation->getReceiver()
                    ->notify(new MessageRead($this->selectedConversation->id));
            }
        }
    }


    public function loadMessages(){
        $count=Message::where('conversation_id',$this->selectedConversation->id)->count();
        $this->loadedMessages=Message::where('conversation_id',$this->selectedConversation->id)
            ->skip($count - $this->paginate_var)
            ->take($this->paginate_var)
        ->get();
        return $this->loadedMessages;
    }
    public function sendMessage(){
        $this->validate(['body'=>'required|string']);
        $createdMessage=Message::create([
            'conversation_id'=>$this->selectedConversation->id,
            'sender_id'=>auth()->id(),
            'receiver_id'=>$this->selectedConversation->getReceiver()->id,
            'body'=>$this->body
        ]);
        $this->reset('body');  

        $this->dispatchBrowserEvent('scroll-bottom');

        $this->loadedMessages->push($createdMessage);

        $this->selectedConversation->updated_at=now();

        $this->selectedConversation->save();

        $this->emitTo('chat.chat-list','refresh');


        // broadcast
        $this->selectedConversation->getReceiver()
        ->notify(new MessageSent(
            Auth()->User(),
            $createdMessage,
            $this->selectedConversation,
            $this->selectedConversation->getReceiver()->id
        ));
    }
    public function mount(){
        $this->loadMessages();
    }
    public function render()
    {
        return view('livewire.chat.chat-box');
    }
}
