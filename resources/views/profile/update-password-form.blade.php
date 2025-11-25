<x-form-section submit="updatePassword">
    <x-slot name="title">Change Password</x-slot>
    <x-slot name="description"></x-slot>

    <x-slot name="form">

        <!-- ✅ FIX: Force this page to ignore Jetstream 6-column grid -->
        <div class="col-span-6 w-full !col-span-6 grid grid-cols-1 !grid-cols-1">

        <div
            x-data="{
                showPw:false,
                pwStrength:'',
                pwMessage:'',
                pwLive:false,
                showCf:false,
                cfStatus:'',
                cfMessage:'',
                cfLive:false,

                init(){
                    if(!$store.passwordStatus){
                        $store.passwordStatus={strength:'',match:'',raw:''};
                    }
                },

                pwBlur(){
                    const pw=this.$refs.pw.value||'';
                    this.evaluatePw(pw);
                    $store.passwordStatus.strength=this.pwStrength;
                    $store.passwordStatus.raw=pw;
                    if(this.pwStrength!=='strong') this.pwLive=true;
                },

                pwInput(){
                    const pw=this.$refs.pw.value||'';
                    if(this.pwLive){
                        this.evaluatePw(pw);
                        $store.passwordStatus.strength=this.pwStrength;
                        $store.passwordStatus.raw=pw;

                        if(this.$refs.cf) this.cfLiveValidate();
                    } else {
                        this.evaluatePw(pw);
                        $store.passwordStatus.strength=this.pwStrength;
                        $store.passwordStatus.raw=pw;
                    }
                },

                evaluatePw(pw){
                    let s=0;
                    if(pw.length>=5) s++;
                    if(pw.length>=10) s++;
                    if(/[A-Z]/.test(pw)) s++;
                    if(/[a-z]/.test(pw)) s++;
                    if(/[0-9]/.test(pw)) s++;
                    if(/[^A-Za-z0-9]/.test(pw)) s++;

                    if(s<=2){ this.pwStrength='weak'; this.pwMessage='Weak password — add uppercase, numbers & symbols.'; }
                    else if(s<=4){ this.pwStrength='medium'; this.pwMessage='Medium strength — add more characters or symbols.'; }
                    else { this.pwStrength='strong'; this.pwMessage='Strong password ✔'; }
                },

                cfBlur(){
                    const pw=$store.passwordStatus.raw||(this.$refs.pw?.value)||'';
                    const cf=this.$refs.cf.value||'';
                    this.compareCf(pw,cf);
                    $store.passwordStatus.match=this.cfStatus;
                    if(this.cfStatus==='mismatch') this.cfLive=true;
                },

                cfInput(){
                    const pw=$store.passwordStatus.raw||(this.$refs.pw?.value)||'';
                    const cf=this.$refs.cf.value||'';
                    this.compareCf(pw,cf);
                    $store.passwordStatus.match=this.cfStatus;
                },

                compareCf(pw,cf){
                    if(!cf){ this.cfStatus=''; this.cfMessage=''; return;}
                    if(pw===cf){ this.cfStatus='match'; this.cfMessage='Password matched ✔'; }
                    else{ this.cfStatus='mismatch'; this.cfMessage='Password does not match ❌'; }
                },

                cfLiveValidate(){
                    const pw=$store.passwordStatus.raw||(this.$refs.pw?.value)||'';
                    const cf=this.$refs.cf?.value||'';
                    if(this.cfLive){
                        this.compareCf(pw,cf);
                        $store.passwordStatus.match=this.cfStatus;
                    }
                }
            }"
            x-init="init()"
            class="space-y-4"
        >

            {{-- CURRENT PASSWORD --}}
            <div class="w-full">
                <div class="relative border rounded-md px-3 py-2 bg-white mt-1">
                    <input id="current_password"
                        placeholder="Current Password"
                        :type="showPw ? 'text' : 'password'"
                        class="block w-full border-none outline-none pr-10 focus:ring-0"
                        wire:model="state.current_password">

                    <button type="button"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-500"
                        @click="showPw=!showPw">
                        <i :class="showPw?'fa-solid fa-eye-slash':'fa-solid fa-eye'"></i>
                    </button>
                </div>
                <x-input-error for="current_password" class="mt-2"/>
            </div>

            {{-- NEW PASSWORD --}}
            <div class="w-full">
                <div class="relative rounded-md px-3 py-2 border bg-white mt-1 transition"
                    :class="{
                        'border-gray-300': pwStrength==='',
                        'border-red-500': pwStrength==='weak',
                        'border-yellow-500': pwStrength==='medium',
                        'border-green-500': pwStrength==='strong'
                    }">
                    <input x-ref="pw"
                        id="password"
                        placeholder="New Password"
                        :type="showPw?'text':'password'"
                        class="block w-full border-none outline-none pr-10 focus:ring-0"
                        wire:model="state.password"
                        @blur="pwBlur"
                        @input="pwInput">

                    <button type="button"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-500"
                        @click="showPw=!showPw">
                        <i :class="showPw?'fa-solid fa-eye-slash':'fa-solid fa-eye'"></i>
                    </button>
                </div>

                <p class="text-sm font-semibold mt-1"
                    :class="{
                        'text-red-500': pwStrength==='weak',
                        'text-yellow-600': pwStrength==='medium',
                        'text-green-600': pwStrength==='strong'
                    }"
                    x-text="pwMessage"></p>

                <x-input-error for="password" class="mt-2"/>
            </div>

            {{-- CONFIRM PASSWORD --}}
            <div class="w-full">
                <div class="relative rounded-md px-3 py-2 border bg-white mt-1 transition"
                    :class="{
                        'border-gray-300': cfStatus==='',
                        'border-red-500': cfStatus==='mismatch',
                        'border-green-500': cfStatus==='match'
                    }">
                    <input x-ref="cf"
                        id="password_confirmation"
                        placeholder="Confirm Password"
                        :type="showCf?'text':'password'"
                        class="block w-full border-none outline-none pr-10 focus:ring-0"
                        wire:model="state.password_confirmation"
                        @blur="cfBlur"
                        @input="cfInput">

                    <button type="button"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-500"
                        @click="showCf=!showCf">
                        <i :class="showCf?'fa-solid fa-eye-slash':'fa-solid fa-eye'"></i>
                    </button>
                </div>

                <p class="text-sm font-semibold mt-1"
                    :class="{
                        'text-red-500': cfStatus==='mismatch',
                        'text-green-600': cfStatus==='match'
                    }"
                    x-text="cfMessage"></p>

                <x-input-error for="password_confirmation" class="mt-2"/>
            </div>

        </div>

        </div> <!-- END FIX WRAPPER -->

    </x-slot>

    <x-slot name="actions">
        <div x-data="{ 
                get canSave(){ 
                    return $store.passwordStatus.strength==='strong' 
                        && $store.passwordStatus.match==='match';
                }
            }"
            class="flex justify-start items-center">

            <x-button type="submit"
                x-bind:disabled="!canSave"
                x-bind:class="canSave
                    ? 'bg-red-500 hover:bg-red-600 text-white'
                    : 'bg-red-400 opacity-60 cursor-not-allowed text-white'">
                Save
            </x-button>
        </div>
    </x-slot>

</x-form-section>
