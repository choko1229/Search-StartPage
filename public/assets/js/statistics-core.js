const providers={search:['google','bing','ddg','yahoo','youtube','github','x','booth','wikipedia','amazon'],ai_search:['chatgpt','claude','grok','gemini']};
const features=['command_palette','background','settings','favorites','sync'];
const identity=value=>typeof value==='string'&&/^[a-f0-9]{32}$/.test(value);
export function statisticsData(type,data={}) {
    if(providers[type])return {provider:providers[type].includes(data.provider)?data.provider:'custom'};
    if(type==='feature'&&features.includes(data.feature))return {feature:data.feature};
    if(['visit','favorite_open'].includes(type))return {};
    throw new Error('INVALID_STATISTICS_EVENT');
}
export function safeStatisticsEvent(event,now) {
    if(!event||Object.keys(event).length!==6||!identity(event.event_id)||!identity(event.anonymous_id)||!['web','extension'].includes(event.source)
        ||!Number.isInteger(event.created_at)||event.created_at<0||event.created_at>now+300||!event.event_data||typeof event.event_data!=='object'||Array.isArray(event.event_data))return false;
    try{return JSON.stringify(statisticsData(event.event_type,event.event_data))===JSON.stringify(event.event_data);}catch{return false;}
}
export class StatisticsQueue {
    constructor(io,{source='web',random=()=>crypto.randomUUID().replaceAll('-',''),clock=()=>Math.floor(Date.now()/1000)}={}) {
        if(!['web','extension'].includes(source))throw new Error('INVALID_STATISTICS_SOURCE');
        Object.assign(this,{io,source,random,clock,busy:false});
    }
    async add(type,data={}) {
        const eventId=this.random(),newIdentity=this.random(),time=this.clock(),details=statisticsData(type,data);
        await this.io.write(current=>{
            const anonymousId=identity(current?.anonymousId)?current.anonymousId:newIdentity;
            const event={event_id:eventId,anonymous_id:anonymousId,event_type:type,event_data:details,source:this.source,created_at:time};
            return {anonymousId,events:[...(Array.isArray(current?.events)?current.events:[]).filter(row=>safeStatisticsEvent(row,time)),event]};
        });
        return eventId;
    }
    async flush() {
        if(this.busy)return;this.busy=true;
        try{
            for(;;){
                const current=this.io.read(),batch=(Array.isArray(current?.events)?current.events:[]).filter(row=>safeStatisticsEvent(row,this.clock())).slice(0,20);
                if(!batch.length)return;
                const response=await this.io.send(batch),accepted=response?.data?.accepted;
                if(response?.status!==200||!Array.isArray(accepted)||accepted.length!==batch.length||batch.some(row=>!accepted.includes(row.event_id)))throw new Error('STATISTICS_DELIVERY_FAILED');
                const delivered=new Set(batch.map(row=>row.anonymous_id+':'+row.event_id));
                await this.io.write(latest=>({...latest,events:(latest.events||[]).filter(row=>!delivered.has(row.anonymous_id+':'+row.event_id))}));
            }
        }finally{this.busy=false;}
    }
}
