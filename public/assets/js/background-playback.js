export class BackgroundPlayback {
    constructor(changed=()=>{},failed=()=>{}){Object.assign(this,{video:null,wanted:false,hidden:false,pending:false,blocked:false,revision:0,changed,failed,listeners:[]});}
    state(){return {available:Boolean(this.video),playing:Boolean(this.video&&!this.video.paused),pending:this.pending};}
    notify(){this.changed(this.state());}
    clear(){
        this.revision++;for(const [name,listener] of this.listeners)this.video?.removeEventListener(name,listener);
        this.listeners=[];this.video?.pause();this.video=null;this.wanted=false;this.pending=false;this.blocked=false;this.notify();
    }
    attach(video,settings,hidden=false){
        this.clear();this.video=video;this.hidden=hidden;this.wanted=settings.autoplay===true&&settings.paused!==true;
        for(const name of ['play','pause','ended']){
            const listener=()=>{if(name==='ended'&&!video.loop)this.wanted=false;this.notify();};
            video.addEventListener(name,listener);this.listeners.push([name,listener]);
        }
        this.sync(hidden);this.notify();
    }
    sync(hidden){
        this.hidden=hidden;if(!this.video)return;
        if(hidden||!this.wanted){this.revision++;this.pending=false;this.video.pause();this.notify();return;}
        if(!this.blocked&&!this.pending&&this.video.paused)this.play();
    }
    toggle(){
        if(!this.video)return;
        if(this.pending||!this.video.paused){this.wanted=false;this.sync(this.hidden);}
        else{this.wanted=true;this.blocked=false;this.sync(this.hidden);}
    }
    play(){
        const video=this.video,revision=++this.revision;this.pending=true;this.notify();
        let result;
        try{result=video.play();}catch{result=Promise.reject(new Error('playback_blocked'));}
        Promise.resolve(result).then(()=>{
            if(video!==this.video||revision!==this.revision){if(video!==this.video||this.hidden||!this.wanted)video.pause();return;}
            this.pending=false;this.notify();
        }).catch(()=>{
            if(video!==this.video||revision!==this.revision)return;
            this.pending=false;this.blocked=true;this.notify();this.failed();
        });
    }
}
