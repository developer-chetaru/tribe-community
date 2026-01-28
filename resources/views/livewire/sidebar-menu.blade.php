<div x-cloak
     x-data="sidebarComponent()"
     :class="sidebarClass"
     class="flex flex-col h-screen bg-white text-black sidebar-menu"
>
    <!-- Header: Logo + Toggle Button -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 flex-shrink-0">
        <template x-if="$store.sidebar.open">
            <a href="/dashboard" class="cursor-pointer hover:opacity-80 transition-opacity">
                <img src="{{ asset('images/logo-tribe.svg') }}" class="w-32" alt="TRIBE 365 Vibe - Go to Dashboard" />
            </a>
        </template>

        <button @click="$store.sidebar.toggle()">
            <!-- Open icon -->
            <template x-if="!$store.sidebar.open">
                <img src="{{ asset('images/navigation-active.svg') }}" class="h-6 w-6" />
            </template>

            <!-- Close icon -->
            <template x-if="$store.sidebar.open">
                <img src="{{ asset('images/navigation-icon.svg') }}" class="h-6 w-6" />
            </template>
        </button>
    </div>
<div class="menu-icon" aria-label="Toggle menu">
    <span class="top"></span>
    <span class="middle"></span>
    <span class="bottom"></span>
</div>
    <!-- Sidebar menu: scrollable -->
    <div  class="p-3 space-y-1 flex-1 overflow-y-auto">
      
            <a href="/dashboard"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
       window.location.pathname === '/dashboard' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <!-- Dynamic image based on active route -->
    <img 
        :src="window.location.pathname === '/dashboard' 
            ? '{{ asset('images/dashboard-active.svg') }}' 
            : '{{ asset('images/dashboard.svg') }}'" 
        class="h-5 w-5" />

    <span x-show="$store.sidebar.open" x-transition class="text-sm">Dashboard</span>
</a>
      
      
      @hasanyrole('super_admin')

    <a href="{{ route('organisations.index') }}"
       class="flex items-center p-2.5 rounded-xl transition"
       :class="[
           $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
           window.location.pathname === '{{ route('organisations.index', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]">

        <!-- Dynamic icon -->
        <img 
            :src="window.location.pathname === '{{ route('organisations.index', [], false) }}' 
                ? '{{ asset('images/organisations-active.svg') }}' 
                : '{{ asset('images/organisations.svg') }}'" 
            class="h-5 w-5" />

        <!-- Show label only if sidebar is open -->
        <span x-show="$store.sidebar.open" x-transition class="text-sm">
            Organisations
        </span>
    </a>
      @endhasanyrole


      
@hasanyrole('organisation_user|organisation_admin|basecamp|director')
    <a href="{{ route('hptm.list') }}"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
      :class="[
               $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
               window.location.pathname.startsWith('{{ route('hptm.list', [], false) }}') 
                   ? 'bg-red-100 text-red-600 font-semibold' 
                   : 'text-gray-700 hover:bg-gray-100'
           ]"
      >
        <img 
                :src="window.location.pathname.startsWith('{{ route('hptm.list', [], false) }}') 
                    ? '{{ asset('images/organisations-active.svg') }}' 
                    : '{{ asset('images/organisations.svg') }}'" 
                class="h-5 w-5" />


        <span x-show="$store.sidebar.open" x-transition class="text-sm">HPTM</span>
    </a>
 @endhasanyrole

@hasanyrole('director|basecamp')
    <a href="{{ route('billing') }}"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
      :class="[
               $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
               window.location.pathname === '/billing' 
                   ? 'bg-red-100 text-red-600 font-semibold' 
                   : 'text-gray-700 hover:bg-gray-100'
           ]"
      >
        <svg class="h-5 w-5" :class="window.location.pathname === '/billing' ? 'text-red-600' : 'text-gray-700'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>

        <span x-show="$store.sidebar.open" x-transition class="text-sm">Billing</span>
    </a>
@endhasanyrole

@hasanyrole('organisation_user|organisation_admin|basecamp|director')
@if(auth()->user()->orgId)
    <a href="{{ route('myteam.list') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
  :class="[
           $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
           window.location.pathname === '{{ route('myteam.list', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]"
  >
    <img 
            :src="window.location.pathname === '{{ route('myteam.list', [], false) }}' 
                ? '{{ asset('images/my-teammate-icon-active.svg') }}' 
                : '{{ asset('images/my-teammate-icon-default.svg') }}'" 
            class="h-5 w-5" />


    <span x-show="$store.sidebar.open" x-transition class="text-sm">My Teammates</span>
</a>
@endif
@endhasanyrole

@hasanyrole('organisation_user|basecamp|organisation_admin|director')
    <a href="{{ route('admin.reflections.index') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
  :class="[
           $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
           window.location.pathname === '{{ route('admin.reflections.index', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]"
  >
    <img :src="window.location.pathname === '{{ route('admin.reflections.index', [], false) }}' ? '{{ asset('images/reflectoin_red.svg') }}' 
                : '{{ asset('images/reflectoin_black.svg') }}'"  class="h-5 w-5" />
    <span x-show="$store.sidebar.open" x-transition class="text-sm">Reflections</span>
</a>
@endhasanyrole

{{-- Notification button removed for users --}}
{{-- @hasanyrole('organisation_user|basecamp|organisation_admin')
    <a href="{{ route('user.notifications') }}"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition relative"
       :class="[
           $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
           window.location.pathname === '{{ route('user.notifications', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]"
    >
        <div class="relative">
            <img :src="window.location.pathname === '{{ route('user.notifications', [], false) }}' 
                        ? '{{ asset('images/notification-active.svg') }}' 
                        : '{{ asset('images/notification.svg') }}'"  
                 class="h-5 w-5" />
            @php
                $notificationCount = \App\Models\IotNotification::where('to_bubble_user_id', auth()->id())
                    ->where('archive', false)
                    ->where(function($q) {
                        // Exclude sentiment reminder notifications
                        $q->where(function($subQuery) {
                            $subQuery->where('notificationType', '!=', 'sentiment-reminder')
                                     ->orWhereNull('notificationType');
                        })
                        ->where(function($subQuery) {
                            $subQuery->where('title', '!=', 'Reminder: Please Update Your Sentiment Index')
                                     ->orWhereNull('title');
                        });
                    })
                    ->count();
            @endphp
            @if($notificationCount > 0)
                <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                    {{ $notificationCount > 9 ? '9+' : $notificationCount }}
                </span>
            @endif
        </div>

        <span x-show="$store.sidebar.open" x-transition class="text-sm">
             Notification
        </span>
    </a>
@endhasanyrole --}}

 <!-- @hasanyrole('super_admin')
    <a href="#"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
       window.location.pathname === '' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <img 
        :src="window.location.pathname === '' 
            ? '{{ asset('images/offloading-active.svg') }}' 
            : '{{ asset('images/offloading.svg') }}'" 
        class="h-5 w-5" />

    <span x-show="$store.sidebar.open" x-transition class="text-sm">Offloading</span>
</a>
     @endhasanyrole
-->

         
      @hasanyrole('super_admin')
      <a href="{{ route('basecampuser') }}"
       class="flex items-center p-2.5 rounded-xl transition"
       :class="[
           $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
           window.location.pathname === '{{ route('basecampuser', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]">

        <!-- Dynamic icon -->
        <img 
            :src="window.location.apthname === '{{ route('basecampuser', [], false) }}' 
                ? '{{ asset('images/basecamnp-user-active.svg') }}' 
                : '{{ asset('images/basecamnp-user-default.svg') }}'" 
            class="h-5 w-5" />

        <!-- Show label only if sidebar is open -->
        <span x-show="$store.sidebar.open" x-transition class="text-sm">
            Basecamp User
        </span>
    </a>
      
        @endhasanyrole
      
	<!--    @hasanyrole('super_admin')

    <a href="#"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
       window.location.pathname === '' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <img 
        :src="window.location.pathname === '' 
            ? '{{ asset('images/risk-active.svg') }}' 
            : '{{ asset('images/risk.svg') }}'" 
        class="h-5 w-5" />

    <span x-show="$store.sidebar.open" x-transition class="text-sm">Risks</span>
</a>
      
     @endhasanyrole  -->
      
<!--    @hasanyrole('super_admin')
    <a href="#"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
       window.location.pathname === '' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <img 
        :src="window.location.pathname === '' 
            ? '{{ asset('images/kudos-active.svg') }}' 
            : '{{ asset('images/kudos.svg') }}'" 
        class="h-5 w-5" />

    <span x-show="$store.sidebar.open" x-transition class="text-sm">Kudos</span>
</a>
   @endhasanyrole
-->
          
    <!-- notification -->
     @hasanyrole('super_admin')
    <a href="{{ route('admin.subscriptions') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
       window.location.pathname === '/admin/subscriptions' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <svg class="h-5 w-5" :class="window.location.pathname === '/admin/subscriptions' ? 'text-red-600' : 'text-gray-700'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>

    <span x-show="$store.sidebar.open" x-transition class="text-sm">Subscriptions</span>
</a>
      
    <a href="{{ route('admin.send-notification') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
       window.location.pathname === '' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <img 
        :src="window.location.pathname === '' 
            ? '{{ asset('images/notification-active.svg') }}' 
            : '{{ asset('images/notification.svg') }}'" 
        class="h-5 w-5" />

    <span x-show="$store.sidebar.open" x-transition class="text-sm">Send Notification</span>
</a>
     
  @endhasanyrole
@hasanyrole('super_admin')
<!-- Settings Menu -->
<div 
    x-data="{ open: {{ request()->is('department*') || request()->is('directing*') || request()->is('connecting*') || request()->is('personality-type*') || request()->is('supercharging*') || request()->is('diagnostics*') || request()->is('principles*') || request()->is('hipb*') || request()->is('tribometer*') 
|| request()->is('reflection*') || request()->is('principles*') || request()->is('industries*')  || request()->is('learning-checklist*') || request()->is('learning-types*') || request()->is('team-feedback*')
 ? 'true' : 'false' }} }">


  <!-- Parent Button -->
<button @click="open = !open"
    :class="[
        'flex items-center w-full p-2.5 rounded-xl transition',
        $store.sidebar.open ? 'space-x-3 justify-start' : 'justify-center',
        '{{ request()->is('department*') || request()->is('directing*') || request()->is('connecting*') || request()->is('personality-type*') || request()->is('supercharging*') || request()->is('diagnostics*') || request()->is('principles*') || request()->is('hipb*') 
             || request()->is('learning-checklist*')  || request()->is('industries*') ||  request()->is('learning-types*') 
            || request()->is('team-feedback*') || request()->is('learning-types*') 
            
         
            ? 'bg-red-100 text-red-600 font-semibold' 
            : 'text-gray-700 hover:bg-gray-100' }}'
    ]">

    <!-- Icon -->
  <img 
    src="{{ request()->is('department*') || request()->is('industries*') || request()->is('directing*') || request()->is('reflection*') || request()->is('principles*') || request()->is('learning-checklist*') || request()->is('learning-types*') || request()->is('team-feedback*') || request()->is('admin/prompts*') ? asset('images/setting-active.svg') : asset('images/setting.svg') }}" 
    class="h-5 w-5" 
/>

    <!-- Text + Arrow only if sidebar open -->
    <template x-if="$store.sidebar.open">
        <div class="flex items-center flex-1 ml-2">
            <span class="text-sm">Universal Setting</span>
            <svg class="w-3 h-3 transition-transform ml-auto"
                 :class="open ? 'rotate-180' : ''"
                 fill="currentColor" viewBox="0 0 448 512">
                <path d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 
                         19.029-46.059h384.569c23.975 0 35.999 29.089 
                         19.029 46.059L240.971 381.476c-10.496 
                         10.496-27.561 10.496-38.057 0z"/>
            </svg>
        </div>
    </template>
</button>

    <!-- Submenus -->
    <div x-show="open && $store.sidebar.open" x-transition 
         class="ml-6 mt-1 space-y-1 border-l border-gray-200 pl-3">

        {{-- Department --}}
        <a href="{{ url('/department') }}"
            class="flex items-center space-x-3  ml-3  p-2 rounded text-sm w-full
            {{ request()->is('department*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
       
            <img 
    src="{{ request()->is('department*')  ? asset('images/departement-active.svg') : asset('images/departement (1).svg') }}" 
    class="h-5 w-5" 
/>


            <span>Department</span>
        </a>

 

        <a href="{{ url('/industries') }}"
            class="flex items-center space-x-3  ml-3  p-2 rounded text-sm w-full
            {{ request()->is('industries*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
       
            <img 
    src="{{ request()->is('industries*')  ? asset('images/industries-icon-active.svg') : asset('images/industries-icon.svg') }}" 
    class="h-5 w-5" 
/>


            <span>Industries</span>
        </a>

 
<!-- DirectingDropdown -->

<div     x-data="{ 
        open: {{ request()->is('directing*')

 ? 'true' : 'false' }} 
    }"  class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700 {{ request()->is('directing*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}
 ">
        <div class="flex items-center space-x-3">
          <img 
    src="{{ request()->is('directing*')  ? asset('images/directing-active.svg') : asset('images/directing.svg') }}" 
    class="h-5 w-5" 
/>

            <span class="text-sm">Directing</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <!-- Dropdown items -->
    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3 ">
        <a href="{{ route('directing-value.list') }}" 
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100 {{ request()->is('directing*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">Directing Values</a>

    </div>
</div>

{{-- Connecting --}}
<!--
<div x-data="{ open: false }" class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700">
        <div class="flex items-center space-x-3">
            <img src="{{ asset('images/connecting.svg') }}" class="h-5 w-5" />
            <span class="text-sm">Connecting</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>
 
    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
        <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Questions</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Description</a>
    </div>
</div>
-->

{{-- personality-type --}}
<!--
<div x-data="{ open: false }" class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700">
        <div class="flex items-center space-x-3">
            <img src="{{ asset('images/personality-type.svg') }}" class="h-5 w-5" />
            <span class="text-sm">Personality Type</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
        <a href="#" class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Question</a>
              <a href="#" class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Options</a>
              <a href="#" class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Value</a>
    </div>
</div>
 -->

{{-- Supercharging --}}
<!--
<div x-data="{ open: false }" class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700">
        <div class="flex items-center space-x-3">
            <img src="{{ asset('images/personality-type.svg') }}" class="h-5 w-5" />
            <span class="text-sm">Supercharging</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
        <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Cultural Structure Questions</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Motivation Questions</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Motivation Value</a>
    </div>
</div>
-->

{{-- Diagnostics --}}
<!--
<div x-data="{ open: false }" class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700">
        <div class="flex items-center space-x-3">
            <img src="{{ asset('images/diagnostics.svg') }}" class="h-5 w-5" />
            <span class="text-sm">Diagnostics</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
        <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Question</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Options</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Value</a>
    </div>
</div>
-->
{{-- Tribometer --}}
<!--
<div x-data="{ open: false }" class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700">
        <div class="flex items-center space-x-3">
            <img src="{{ asset('images/tribometer.svg') }}" class="h-5 w-5" />
            <span class="text-sm">Tribometer</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
       <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Question</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Options</a>
              <a href="#"
            class="block text-sm text-gray-700 p-2 rounded hover:bg-gray-100">Value</a>
    </div>
</div>
-->

{{-- HI-PB’S --}}
      
<div 
    x-data="{ 
        open: {{ request()->is('reflection*') || request()->is('principles*') || request()->is('learning-checklist*') || request()->is('learning-types*') || request()->is('team-feedback*')

 ? 'true' : 'false' }} 
    }" 
    class="ml-3 space-y-1"
>
    <!-- Parent Button -->
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl transition 
        {{ request()->is('reflection*') || request()->is('principles*') || request()->is('learning-checklist*') || request()->is('learning-types*') || request()->is('team-feedback*') ? 'bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100' }}">
        <div class="flex items-center space-x-3">
            <img 
    src="{{ request()->is('reflection*') || request()->is('principles*') || request()->is('learning-types*') ||
         request()->is('learning-checklist*') ||  request()->is('team-feedback*') ? asset('images/conversation-active.svg') : asset('images/hipb.svg') }}" 
    class="h-5 w-5" 
/>

          
            <span class="text-sm">HPTM</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <!-- Dropdown items -->
    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
        <a href="{{ route('admin.reflections.index') }}" class="block text-sm p-2 rounded {{ request()->routeIs('admin.reflections.index') ? 'bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100' }}">
            Reflection
        </a>
        <a href="{{ route('principles') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('principles*') ? 'bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100' }}">
            Principles
        </a>
        <a href="{{ route('learningchecklist.list') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('learning-checklist*') ? 'bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100' }}">
            Learning Checklist
        </a>
        <a href="{{ route('learningtype.list') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('learning-types*') ? 'bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100' }}">
            Checklist Type
        </a>
        <a href="{{ route('team-feedback.list') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('team-feedback*') ? 'bg-red-100 text-red-600' : 'text-gray-700 hover:bg-gray-100' }}">
            Individual Questionnaire
        </a>
        <a href="{{ route('admin.prompts') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/prompts*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            AI Prompts
        </a>
    </div>
</div>

     
    </div>
</div>
@endhasanyrole

    </div>
</div>

<!-- Alpine Global Store + Sidebar Component -->
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('sidebar', {
            open: true,
            menus: {
                personality: false
            },
            toggle() {
                this.open = !this.open;
            },
            toggleMenu(name) {
                this.menus[name] = !this.menus[name];
            },
            isOpen(name) {
                return this.menus[name];
            }
        });

        Alpine.data('sidebarComponent', () => ({
            get sidebarClass() {
                return {
                    'w-64': Alpine.store('sidebar').open,
                    'w-20': !Alpine.store('sidebar').open,
                    'bg-white text-black h-screen sticky top-0 left-0 z-40 transition-all duration-300': true
                };
            }
        }));
    });

document.querySelector(".menu-icon").addEventListener("click", function () {
    document.querySelector(".menu-icon").classList.toggle("active");
    document.body.classList.toggle("menu-open");
});
</script>



<style type="text/css">
    .menu-icon {display: none;}
    @media (max-width:992px) {
        nav.bg-white {
            padding-left: 60px !important;
            min-height: 58px;
        }
        
        nav.bg-white header {
            margin-left: 0;
            padding-left: 0;
        }
        
        .menu-icon {
            width:34px;
            height: 34px;
            background-color: #EB1C24;
            left: 12px;
            border-radius: 3px;
            top: 12px;
            position: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: none;
            z-index: 1001;
        }
            
            .menu-icon:hover {
                background-color: #c71313;
                box-shadow:none;
                transform: scale(1.05);
            }
            
            .menu-icon:active {
                transform: scale(0.95);
            }

            .sidebar-menu img.w-32 {
                margin-left: -9px;
            }

            .sidebar-menu {
                position: fixed;
                left: -225px;
                z-index: 999;
                width: 223px;
                height: 100vh;
     
            }

            .menu-open .sidebar-menu {
                left: 0;
            }

            .menu-icon span {
                width: 24px;
                height: 2.5px;
                background-color: #ffffff;
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                border-radius: 2px;
            }

            .menu-icon span.top {
                top: 9px;
            }

            .menu-icon span.middle {
                top: 16px;
            }

            .menu-icon span.bottom {
                top: 23px;
            }

            .menu-icon.active {
                left: 172px;
                background-color: #EB1C24;
            }

            .menu-open .menu-icon span.middle {
                opacity: 0;
                transform: translateX(-50%) scale(0);
            }

            .menu-open .menu-icon span.top {
                top: 17px;
                transform: translateX(-50%) translateY(-50%) rotate(45deg);
                width: 24px;
            }

            .menu-open .menu-icon span.bottom {
                top: 17px;
                transform: translateX(-50%) translateY(-50%) rotate(-45deg);
                width: 24px;
            }

.sidebar-menu:after {
    content: "";
    position: absolute;
    width: calc(100vw - 225px);
    height: 100%;
    background-color: rgba(255 255 255 / 0.5);
    left: -100vw;
    opacity: 0;
    transition: all 0.5s ease-in-out;
    z-index: -1;
}

.sidebar-menu > div {
    /* z-index: 9; */
    /* position: relative; */
}

.sidebar-menu img.h-6.w-6 {
    display: none !important;
}

.menu-open .sidebar-menu:after {
    opacity: 1;
    left: 225px;
}
    }
</style>