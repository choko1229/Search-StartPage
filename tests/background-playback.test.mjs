import assert from 'node:assert/strict';
import {BackgroundPlayback} from '../public/assets/js/background-playback.js';
class Video extends EventTarget {
    constructor(){super();this.paused=true;this.loop=false;this.plays=0;this.rejected=false;}
    play(){this.plays++;if(this.rejected)return Promise.reject(new Error('blocked'));this.paused=false;this.dispatchEvent(new Event('play'));return Promise.resolve();}
    pause(){if(!this.paused){this.paused=true;this.dispatchEvent(new Event('pause'));}}
}
const settle=async()=>{await Promise.resolve();await Promise.resolve();};
let failure=0,state;
const controller=new BackgroundPlayback(value=>state=value,()=>failure++),video=new Video();
controller.attach(video,{autoplay:false});assert.equal(video.plays,0);assert.equal(state.available,true);
controller.toggle();await settle();assert.equal(video.paused,false);assert.equal(state.playing,true);
controller.toggle();controller.sync(false);assert.equal(video.paused,true);assert.equal(video.plays,1);
controller.toggle();await settle();controller.sync(true);assert.equal(video.paused,true);controller.sync(false);await settle();assert.equal(video.paused,false);
video.paused=true;video.dispatchEvent(new Event('ended'));controller.sync(false);assert.equal(video.plays,3);
controller.toggle();await settle();assert.equal(video.plays,4);
controller.clear();assert.equal(state.available,false);assert.equal(video.paused,true);
const blocked=new Video();blocked.rejected=true;controller.attach(blocked,{autoplay:true});await settle();assert.equal(failure,1);controller.sync(false);assert.equal(blocked.plays,1);
blocked.rejected=false;controller.toggle();await settle();assert.equal(blocked.paused,false);
const paused=new Video();controller.attach(paused,{autoplay:true,paused:true});assert.equal(paused.plays,0);controller.toggle();await settle();assert.equal(paused.paused,false);
const deferred=new Video();let finish;deferred.play=function(){this.plays++;return new Promise(resolve=>{finish=()=>{this.paused=false;resolve();};});};
controller.attach(deferred,{autoplay:true});controller.toggle();finish();await settle();assert.equal(deferred.paused,true);
controller.attach(deferred,{autoplay:true});const previous=finish;controller.clear();previous();await settle();assert.equal(deferred.paused,true);
console.log('Background playback: manual play/pause, hidden resume, ended, blocked retry, configured pause, late play and detach passed.');
