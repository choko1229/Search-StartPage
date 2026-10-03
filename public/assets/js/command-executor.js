import {requiresCommandConfirmation} from './command-registry.js';

/** UI supplies confirmation and atomic preference persistence; commands own effects. */
export class CommandExecutor {
    #busy=false;
    constructor(registry,io){this.registry=registry;this.io=io;}
    async execute(id,context) {
        if(this.#busy)throw new Error('COMMAND_BUSY');
        const command=this.registry.get(id);if(!command)throw new Error('COMMAND_UNAVAILABLE');
        this.#busy=true;
        try {
            if(requiresCommandConfirmation(command,this.io.preferences())) {
                const choice=await this.io.confirm(command);
                if(choice?.confirmed!==true)return {executed:false};
                if(this.registry.get(id)!==command)throw new Error('COMMAND_UNAVAILABLE');
                if(choice.remember===true)await this.io.remember(command.confirmationKey,false);
            }
            if(this.registry.get(id)!==command)throw new Error('COMMAND_UNAVAILABLE');
            const value=await command.run(context);
            return {executed:true,value};
        } finally {this.#busy=false;}
    }
}
