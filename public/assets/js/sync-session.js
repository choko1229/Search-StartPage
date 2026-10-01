import {mergeSync, resolveSync, equal} from './sync-core.js';
export const syncInterval = idleMs => idleMs < 10000 ? 10000 : idleMs < 300000 ? 60000 : 300000;
const populated = document => Object.values(document || {}).some(value => Object.keys(value || {}).length > 0);

// Transport, persistence and dialogs are injected so Web and Extension share
// the same synchronization protocol without depending on a UI framework.
export class SyncSession {
    constructor(adapters) {this.io = adapters; this.busy = false; this.paused = false;}
    async run() {
        if (this.busy || this.paused) return false;
        this.busy = true;
        try {
            const user = await this.io.user();
            if (!user) {this.io.status('signed_out');return false;}
            let checkpoint = this.io.checkpoint(user.id);
            let cloud = await this.io.read();
            let initialChoice = null;
            if (!checkpoint) {
                const local = this.io.local(checkpoint);
                if (populated(local) && populated(cloud.document)) initialChoice = await this.io.initial();
                else initialChoice = populated(cloud.document) ? 'cloud' : 'local';
                if (initialChoice === 'later') {this.paused = true;this.io.status('later');return false;}
                if (!['local','cloud'].includes(initialChoice)) throw new Error('invalid_initial_choice');
            }
            for (let attempt = 0; attempt < 3; attempt++) {
                const local = this.io.local(checkpoint);
                let target;
                const localRules=local.settings?.syncRules, baseRules=checkpoint?.document.settings?.syncRules;
                let rules=baseRules!==undefined && localRules===undefined ? {}
                    : equal(localRules,baseRules) ? (cloud.document.settings?.syncRules ?? localRules ?? checkpoint?.rules ?? {})
                    : (localRules ?? cloud.document.settings?.syncRules ?? checkpoint?.rules ?? {});
                if (!checkpoint) target = initialChoice === 'cloud' ? cloud.document : local;
                else {
                    const merged = mergeSync(checkpoint.document, local, cloud.document, rules);
                    if (merged.conflicts.length) {
                        const answer = await this.io.conflicts(merged.conflicts);
                        if (!answer) {this.paused = true;this.io.status('conflict');return false;}
                        target = resolveSync(checkpoint.document, local, cloud.document, answer.choices, rules);
                        rules = {...rules,...answer.rules};
                        if(Object.keys(answer.rules || {}).length) {target.settings??={};target.settings.syncRules=rules;}
                    } else target = merged.data;
                }
                // Upgrade older locally saved rules into the shared settings.
                if(checkpoint && baseRules===undefined && Object.keys(rules).length && !target.settings?.syncRules) {target.settings??={};target.settings.syncRules=rules;}
                rules=target.settings?.syncRules || {};
                if (this.io.current && !await this.io.current(user.id)) return false;
                const response = equal(target,cloud.document) ? {status:200,data:cloud} : await this.io.write(cloud.version, target, String(user.id));
                if (response.status === 409) {
                    cloud = response.data;
                    // Reconfirm first-login choices after concurrent cloud updates.
                    if (!checkpoint) {
                        initialChoice = await this.io.initial();
                        if (initialChoice === 'later') {this.paused=true;this.io.status('later');return false;}
                        if (!['local','cloud'].includes(initialChoice)) throw new Error('invalid_initial_choice');
                    }
                    continue;
                }
                if (response.status !== 200) throw new Error('sync_failed');
                if (this.io.current && !await this.io.current(user.id)) return false;
                const acknowledged = response.data;
                // Edits made while the request was in flight stay local and are
                // merged against this acknowledged base on the next run.
                const latest = mergeSync(local, this.io.local(checkpoint), acknowledged.document);
                if (latest.conflicts.length) throw new Error('unexpected_acknowledgement');
                checkpoint = {userId:String(user.id),version:acknowledged.version,document:acknowledged.document,rules,preferences:this.io.preferences?.() || {}};
                await this.io.accept(latest.data, checkpoint);
                this.io.status('synced');return true;
            }
            throw new Error('sync_retry_exhausted');
        } finally {this.busy = false;}
    }
}
