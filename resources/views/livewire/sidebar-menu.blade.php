<div>
<div 
     x-data="sidebarComponent()"
     :class="sidebarClass"
     class="flex flex-col h-screen bg-white text-black sidebar-menu w-64"
>
    <!-- Header: Logo + Toggle Button -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 flex-shrink-0 h-[77px]">
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
       $store.sidebar.open ? ' justify-start' : 'justify-center',
       window.location.pathname === '/dashboard' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <!-- Dynamic image based on active route -->
    <img src="{{ request()->is('dashboard*')  ? asset('images/dashboard-active.svg') : asset('images/dashboard.svg') }}" 
    class="h-5 w-5" />

    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Dashboard</span>
</a>
      
      
      @hasanyrole('super_admin')

    <a href="{{ route('organisations.index') }}"
       class="flex items-center p-2.5 rounded-xl transition"
       :class="[
           $store.sidebar.open ? ' justify-start' : 'justify-center',
           window.location.pathname === '{{ route('organisations.index', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]">

 <!-- Dynamic image based on active route -->
    <img src="{{ request()->is('organisations*')  ? asset('images/organisations-active.svg') : asset('images/organisations.svg') }}" 
    class="h-5 w-5" />
        <!-- Show label only if sidebar is open -->
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">
            Organisations
        </span>
    </a>
      @endhasanyrole


@hasanyrole('super_admin')
    <a href="#"
       onclick="event.preventDefault(); if(typeof showOffloadingAccess === 'function') { showOffloadingAccess(); }"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
       :class="[
           $store.sidebar.open ? ' justify-start' : 'justify-center',
           window.location.pathname.startsWith('/admin/iot') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
       ]">
        <img
            :src="'{{ asset('images/offloading.svg') }}'"
            class="h-5 w-5" />
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Offloading</span>
    </a>
@endhasanyrole

      
@hasanyrole('organisation_user|organisation_admin|basecamp|director')
    <a href="{{ route('hptm.list') }}"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
      :class="[
               $store.sidebar.open ? ' justify-start' : 'justify-center',
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


        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">HPTM</span>
    </a>
 @endhasanyrole

@hasanyrole('director|basecamp')
    <a href="{{ route('billing') }}"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
      :class="[
               $store.sidebar.open ? ' justify-start' : 'justify-center',
               window.location.pathname === '/billing' 
                   ? 'bg-red-100 text-red-600 font-semibold' 
                   : 'text-gray-700 hover:bg-gray-100'
           ]"
      >
        <svg class="h-5 w-5" :class="window.location.pathname === '/billing' ? 'text-red-600' : 'text-gray-700'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>

        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Billing</span>
    </a>
@endhasanyrole

@hasanyrole('organisation_user|organisation_admin|basecamp|director')
@if(auth()->user()->orgId)
    <a href="{{ route('myteam.list') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
  :class="[
           $store.sidebar.open ? ' justify-start' : 'justify-center',
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


    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">My Teammates</span>
</a>
@endif
@endhasanyrole

@hasanyrole('organisation_user|basecamp|organisation_admin|director')
    <a href="{{ route('admin.reflections.index') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
  :class="[
           $store.sidebar.open ? ' justify-start' : 'justify-center',
           window.location.pathname === '{{ route('admin.reflections.index', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]"
  >
    <img :src="window.location.pathname === '{{ route('admin.reflections.index', [], false) }}' ? '{{ asset('images/reflectoin_red.svg') }}' 
                : '{{ asset('images/reflectoin_black.svg') }}'"  class="h-5 w-5" />
    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Reflections</span>
</a>
@endhasanyrole

<!-- Offloading for regular users -->
@hasanyrole('organisation_user|director')
@if(!auth()->user()->hasAnyRole(['super_admin', 'organisation_admin']))
<a href="{{ route('offloading.list') }}"
   class="flex items-center p-2.5 rounded-xl transition
       {{ request()->is('offloading*') || request()->routeIs('offloading.*')
           ? 'bg-red-100 text-red-600 font-semibold'
           : 'text-gray-700 hover:bg-gray-100' }}"
   :class="$store.sidebar.open ? 'justify-start' : 'justify-center'">
    <img src="{{ request()->is('offloading*') || request()->routeIs('offloading.*') ? asset('images/offloading-active.svg') : asset('images/offloading.svg') }}"
         class="h-5 w-5 flex-shrink-0"
         onerror="this.src='{{ asset('images/offloading.svg') }}'" />
    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Offloading</span>
</a>
@endif
@endhasanyrole

{{-- Notification button removed for users --}}
{{-- @hasanyrole('organisation_user|basecamp|organisation_admin')
    <a href="{{ route('user.notifications') }}"
       class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition relative"
       :class="[
           $store.sidebar.open ? ' justify-start' : 'justify-center',
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

        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">
             Notification
        </span>
    </a>
@endhasanyrole --}}

{{-- Offloading (super_admin placeholder - disabled) --}}

@hasanyrole('organisation_user|organisation_admin')
    {{-- Assessments --}}
    <a href="{{ route('connecting.team-role-map.results') }}"
       class="flex items-center p-2.5 rounded-xl transition
           {{ request()->is('connecting*') || request()->is('personality-type*')
               ? 'bg-red-100 text-red-600 font-semibold'
               : 'text-gray-700 hover:bg-gray-100' }}"
       :class="$store.sidebar.open ? 'justify-start' : 'justify-center'"
    >
        <img src="{{ request()->is('connecting*') || request()->is('personality-type*') ? asset('images/connecting-active.svg') : asset('images/connecting.svg') }}"
             class="h-5 w-5 flex-shrink-0"
             onerror="this.src='{{ asset('images/connecting.svg') }}'" />
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Assessments</span>
    </a>

    {{-- Supercharging --}}
    <a href="{{ route('supercharging.culture-structure.results') }}"
       class="flex items-center p-2.5 rounded-xl transition
           {{ request()->is('supercharging*')
               ? 'bg-red-100 text-red-600 font-semibold'
               : 'text-gray-700 hover:bg-gray-100' }}"
       :class="$store.sidebar.open ? 'justify-start' : 'justify-center'"
    >
        <img src="{{ request()->is('supercharging*') ? asset('images/connecting-active.svg') : asset('images/connecting.svg') }}"
             class="h-5 w-5 flex-shrink-0"
             onerror="this.src='{{ asset('images/connecting.svg') }}'" />
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Supercharging</span>
    </a>

    {{-- Diagnostics --}}
    <a href="{{ route('diagnostics.index') }}"
       class="flex items-center p-2.5 rounded-xl transition
           {{ request()->is('diagnostics*')
               ? 'bg-red-100 text-red-600 font-semibold'
               : 'text-gray-700 hover:bg-gray-100' }}"
       :class="$store.sidebar.open ? 'justify-start' : 'justify-center'"
    >
        <img src="{{ request()->is('diagnostics*') ? asset('images/diagnostics-active.svg') : asset('images/diagnostics.svg') }}"
             class="h-5 w-5 flex-shrink-0"
             onerror="this.src='{{ asset('images/diagnostics.svg') }}'" />
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Diagnostics</span>
    </a>

    {{-- Tribeometer --}}
    <a href="{{ route('tribeometer.index') }}"
       class="flex items-center p-2.5 rounded-xl transition
           {{ request()->is('tribeometer*')
               ? 'bg-red-100 text-red-600 font-semibold'
               : 'text-gray-700 hover:bg-gray-100' }}"
       :class="$store.sidebar.open ? 'justify-start' : 'justify-center'"
    >
        <img src="{{ request()->is('tribeometer*') ? asset('images/diagnostics-active.svg') : asset('images/diagnostics.svg') }}"
             class="h-5 w-5 flex-shrink-0"
             onerror="this.src='{{ asset('images/diagnostics.svg') }}'" />
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Tribeometer</span>
    </a>
@endhasanyrole

<a href="{{ route('help.support') }}"
   class="flex items-center p-2.5 rounded-xl transition
       {{ request()->routeIs('help.support')
           ? 'bg-red-100 text-red-600 font-semibold'
           : 'text-gray-700 hover:bg-gray-100' }}"
   :class="$store.sidebar.open ? 'justify-start' : 'justify-center'">
    <svg class="h-5 w-5 flex-shrink-0 {{ request()->routeIs('help.support') ? 'text-red-600' : 'text-gray-600' }}"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Help & Support</span>
</a>
      @hasanyrole('super_admin')
      <a href="{{ route('basecampuser') }}"
       class="flex items-center p-2.5 rounded-xl transition"
       :class="[
           $store.sidebar.open ? ' justify-start' : 'justify-center',
           window.location.pathname === '{{ route('basecampuser', [], false) }}' 
               ? 'bg-red-100 text-red-600 font-semibold' 
               : 'text-gray-700 hover:bg-gray-100'
       ]">

   <img src="{{ request()->is('basecampuser*')  ? asset('images/basecamnp-user-active.svg') : asset('images/basecamnp-user-default.svg') }}" 
    class="h-5 w-5" />
        <!-- Show label only if sidebar is open -->
        <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">
            Basecamp User
        </span>
    </a>
      
        @endhasanyrole
      
{{-- Risks (disabled) --}}
      
{{-- Kudos (disabled) --}}
          
    <!-- notification -->
     @hasanyrole('super_admin')
    <a href="{{ route('admin.subscriptions') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? ' justify-start' : 'justify-center',
       window.location.pathname === '/admin/subscriptions' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <svg class="h-5 w-5" :class="window.location.pathname === '/admin/subscriptions' ? 'text-red-600' : 'text-gray-700'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>

    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Subscriptions</span>
</a>
      
    <a href="{{ route('admin.send-notification') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? ' justify-start' : 'justify-center',
       window.location.pathname === '{{ route('admin.send-notification', [], false) }}' 
           ? 'bg-red-100 text-red-600 font-semibold' 
           : 'text-gray-700'
   ]">

 <img src="{{ request()->is('send-notification*')  ? asset('images/notification-active.svg') : asset('images/notification.svg') }}" 
    class="h-5 w-5" />
    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Send Notification</span>
</a>
    
    <a href="{{ route('admin.activity-log') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? ' justify-start' : 'justify-center',
       window.location.pathname === '{{ route('admin.activity-log', [], false) }}' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <svg class="h-5 w-5" :class="window.location.pathname === '{{ route('admin.activity-log', [], false) }}' ? 'text-red-600' : 'text-gray-700'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>

    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Activity Log</span>
</a>
    
    <a href="{{ route('admin.login-sessions') }}"
   class="flex items-center p-2.5 rounded-xl hover:bg-gray-100 transition"
   :class="[
       $store.sidebar.open ? ' justify-start' : 'justify-center',
       window.location.pathname === '{{ route('admin.login-sessions', [], false) }}' ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700'
   ]">
    
    <svg class="h-5 w-5" :class="window.location.pathname === '{{ route('admin.login-sessions', [], false) }}' ? 'text-red-600' : 'text-gray-700'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
    </svg>

    <span x-show="$store.sidebar.open" x-transition class="text-sm pl-4">Login Sessions</span>
</a>
     
  @endhasanyrole
@hasanyrole('super_admin')
<!-- Settings Menu -->
<div 
    x-data="{ open: {{ request()->is('department*') || request()->is('directing*')  || request()->is('dashboard*') || request()->is('organisations*') || request()->is('basecampuser*')  || request()->is('send-notification*')  ||  request()->is('connecting*') || request()->is('personality-type*') || request()->is('supercharging*') || request()->is('diagnostics*') || request()->is('principles*') || request()->is('hipb*') || request()->is('tribometer*') 
|| request()->is('reflection*') || request()->is('principles*') || request()->is('industries*')  || request()->is('learning-checklist*') || request()->is('learning-types*') || request()->is('team-feedback*')
 ? 'true' : 'false' }} }">


  <!-- Parent Button -->
<button @click="open = !open"
    :class="[
        'flex items-center w-full p-2.5 rounded-xl transition',
        $store.sidebar.open ? ' justify-start' : 'justify-center',
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
         class="mt-1 space-y-1  border-gray-200 pl-3">

        {{-- Department --}}
        <a href="{{ url('/department') }}"
            class="flex items-center  ml-3  p-2 rounded text-sm w-full
            {{ request()->is('department*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
       
            <img 
    src="{{ request()->is('department*')  ? asset('images/departement-active.svg') : asset('images/departement (1).svg') }}" 
    class="h-5 w-5" 
/>


            <span class="pl-3">Department</span>
        </a>

 

        <a href="{{ url('/industries') }}"
            class="flex items-center ml-3  p-2 rounded text-sm w-full
            {{ request()->is('industries*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
       
            <img 
    src="{{ request()->is('industries*')  ? asset('images/industries-icon-active.svg') : asset('images/industries-icon.svg') }}" 
    class="h-5 w-5" 
/>


            <span class="pl-3">Industries</span>
        </a>

 
<!-- DirectingDropdown -->

<div     x-data="{ 
        open: {{ request()->is('directing*')

 ? 'true' : 'false' }} 
    }"  class="ml-3 space-y-1">
    <button @click="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl hover:bg-gray-100 transition text-gray-700 {{ request()->is('directing*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}
 ">
        <div class="flex items-center ">
          <img 
    src="{{ request()->is('directing*')  ? asset('images/directing-active.svg') : asset('images/directing.svg') }}" 
    class="h-5 w-5" 
/>

            <span class="text-sm pl-3">Directing</span>
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

{{-- Connecting (Team Role Map) --}}
<div 
    x-data="{ 
        open: {{ request()->is('admin/connecting/team-role-map*') ? 'true' : 'false' }} 
    }" 
    class="ml-3 space-y-1">
    <button @click.stop="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl transition 
        {{ request()->is('admin/connecting/team-role-map*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
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
        <a href="{{ route('admin.cot.questions.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/team-role-map/questions*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Team Role Map Questions
        </a>
        <a href="{{ route('admin.cot.team-role-descriptions.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/team-role-map/descriptions*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Team Role Map Description
        </a>
        <a href="{{ route('admin.cot.team-role-results.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/team-role-map/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Team Role Map Results
        </a>
    </div>
</div>

{{-- Personality Type --}}
<div 
    x-data="{ 
        open: {{ request()->is('admin/connecting/personality-type*') ? 'true' : 'false' }} 
    }" 
    class="ml-3 space-y-1">
    <button @click.stop="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl transition 
        {{ request()->is('admin/connecting/personality-type*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
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
        <a href="{{ route('admin.personality-type.questions.index') }}" 
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/personality-type/questions*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Questions
        </a>
        <a href="{{ route('admin.personality-type.options.index') }}" 
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/personality-type/options*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Options
        </a>
        <a href="{{ route('admin.personality-type.values.index') }}" 
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/personality-type/values*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Values
        </a>
        <a href="{{ route('admin.personality-type.results.index') }}" 
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/connecting/personality-type/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Results
        </a>
    </div>
</div>

{{-- Supercharging Module --}}
<div 
    x-data="{ 
        open: {{ request()->is('admin/supercharging*') ? 'true' : 'false' }} 
    }" 
    class="ml-3 space-y-1">
    <button @click.stop="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl transition 
        {{ request()->is('admin/supercharging*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
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
        {{-- Culture Structure --}}
        <div class="ml-2 space-y-1">
            <a href="{{ route('admin.culture-structure.questions.index') }}" 
                class="block text-sm p-2 rounded 
                {{ request()->is('admin/supercharging/culture-structure/questions*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Culture Structure Questions
            </a>
            <a href="{{ route('admin.culture-structure.types.index') }}" 
                class="block text-sm p-2 rounded 
                {{ request()->is('admin/supercharging/culture-structure/types*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Culture Structure Types
            </a>
            <a href="{{ route('admin.culture-structure.results.index') }}" 
                class="block text-sm p-2 rounded 
                {{ request()->is('admin/supercharging/culture-structure/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Culture Structure Results
            </a>
        </div>
        {{-- Motivation --}}
        <div class="ml-2 space-y-1">
            <a href="{{ route('admin.motivation.questions.index') }}" 
                class="block text-sm p-2 rounded 
                {{ request()->is('admin/supercharging/motivation/questions*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Motivation Questions
            </a>
            <a href="{{ route('admin.motivation.values.index') }}" 
                class="block text-sm p-2 rounded 
                {{ request()->is('admin/supercharging/motivation/values*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Motivation Values
            </a>
            <a href="{{ route('admin.motivation.results.index') }}" 
                class="block text-sm p-2 rounded 
                {{ request()->is('admin/supercharging/motivation/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Motivation Results
            </a>
        </div>
    </div>
</div>

{{-- Diagnostics --}}
<div 
    x-data="{ 
        open: {{ request()->is('admin/diagnostics*') ? 'true' : 'false' }} 
    }" 
    class="ml-3 space-y-1">
    <button @click.stop="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl transition 
        {{ request()->is('admin/diagnostics*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
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
        <a href="{{ route('admin.diagnostic.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/diagnostics') && !request()->is('admin/diagnostics/options*') && !request()->is('admin/diagnostics/categories*') && !request()->is('admin/diagnostics/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Questions
        </a>
        <a href="{{ route('admin.diagnostic.options.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/diagnostics/options*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Options
        </a>
        <a href="{{ route('admin.diagnostic.categories.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/diagnostics/categories*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Categories
        </a>
        <a href="{{ route('admin.diagnostic.results.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/diagnostics/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Results
        </a>
    </div>
</div>

{{-- Tribeometer --}}
<div 
    x-data="{ 
        open: {{ request()->is('admin/tribeometer*') ? 'true' : 'false' }} 
    }" 
    class="ml-3 space-y-1">
    <button @click.stop="open = !open"
        class="flex items-center justify-between w-full p-2.5 rounded-xl transition 
        {{ request()->is('admin/tribeometer*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
        <div class="flex items-center space-x-3">
            <img src="{{ asset('images/diagnostics.svg') }}" class="h-5 w-5" />
            <span class="text-sm">Tribeometer</span>
        </div>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="currentColor"
            viewBox="0 0 448 512">
            <path
                d="M207.029 381.476L12.686 187.132c-16.97-16.97-4.946-46.059 19.029-46.059h384.569c23.975 0 35.999 29.089 19.029 46.059L240.971 381.476c-10.496 10.496-27.561 10.496-38.057 0z" />
        </svg>
    </button>

    <div x-show="open" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-200 pl-3">
        <a href="{{ route('admin.tribeometer.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/tribeometer') && !request()->is('admin/tribeometer/options') && !request()->is('admin/tribeometer/values') && !request()->is('admin/tribeometer/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Questions
        </a>
        <a href="{{ route('admin.tribeometer.option.list') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/tribeometer/options*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Options
        </a>
        <a href="{{ route('admin.tribeometer.value.list') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/tribeometer/values*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Values
        </a>
        <a href="{{ route('admin.tribeometer.results.index') }}"
            class="block text-sm p-2 rounded 
            {{ request()->is('admin/tribeometer/results*') ? 'bg-red-100 text-red-600 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
            Results
        </a>
    </div>
</div>

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
        <div class="flex items-center">
            <img 
    src="{{ request()->is('reflection*') || request()->is('principles*') || request()->is('learning-types*') ||
         request()->is('learning-checklist*') ||  request()->is('team-feedback*') ? asset('images/conversation-active.svg') : asset('images/hipb.svg') }}" 
    class="h-5 w-5" 
/>

          
            <span class="text-sm pl-3">HPTM</span>
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

<!-- Offloading Password Modal (Global) -->
<div id="offloadingPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Access Offloading Feature</h2>
        <p class="text-sm text-gray-600 mb-4">Select an organisation and enter your login password to access the Offloading dashboard.</p>

        <form id="offloadingPasswordFormSidebar" onsubmit="event.preventDefault(); verifyOffloadingPasswordFromSidebar();">
            <div class="mb-4">
                <label for="offloadingOrgSelect" class="block text-sm font-medium text-gray-700 mb-2">
                    Organisation <span class="text-red-500">*</span>
                </label>
                <select id="offloadingOrgSelect"
                        required
                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]">
                    <option value="">-- Select Organisation --</option>
                    @forelse($organisations ?? [] as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @empty
                        <option value="" disabled>No organisations found</option>
                    @endforelse
                </select>
            </div>

            <div class="mb-4">
                <label for="offloadingPasswordSidebar" class="block text-sm font-medium text-gray-700 mb-2">
                    Password <span class="text-red-500">*</span>
                </label>
                <input type="password"
                       id="offloadingPasswordSidebar"
                       name="password"
                       required
                       class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#EB1C24]"
                       placeholder="Enter password">
            </div>

            <div id="passwordErrorSidebar" class="mb-4 text-sm text-red-600 hidden"></div>

            <div class="flex justify-end gap-3">
                <button type="button"
                        onclick="closeOffloadingPasswordModalSidebar()"
                        class="px-4 py-2 bg-gray-300 rounded-md hover:bg-gray-400">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-[#EB1C24] text-white rounded-md hover:bg-[#c71313]">
                    Access Dashboard
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.showOffloadingAccess = function() {
        const modal = document.getElementById('offloadingPasswordModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.getElementById('offloadingPasswordSidebar')?.focus();
        }
    };

    function closeOffloadingPasswordModalSidebar() {
        document.getElementById('offloadingPasswordModal')?.classList.add('hidden');
        const password = document.getElementById('offloadingPasswordSidebar');
        const org = document.getElementById('offloadingOrgSelect');
        const err = document.getElementById('passwordErrorSidebar');
        if (password) password.value = '';
        if (org) org.value = '';
        if (err) err.classList.add('hidden');
    }

    function verifyOffloadingPasswordFromSidebar() {
        const password = document.getElementById('offloadingPasswordSidebar')?.value;
        const orgId = document.getElementById('offloadingOrgSelect')?.value;
        const errorDiv = document.getElementById('passwordErrorSidebar');

        if (!orgId) {
            errorDiv.textContent = 'Please select an organisation';
            errorDiv.classList.remove('hidden');
            return;
        }

        fetch('{{ route("admin.improvement.check-pass") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ password: password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                window.location.href = '/admin/iot-dashboard/' + orgId;
            } else {
                errorDiv.textContent = data.message || 'Invalid password';
                errorDiv.classList.remove('hidden');
            }
        })
        .catch(() => {
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.classList.remove('hidden');
        });
    }

    document.getElementById('offloadingPasswordModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeOffloadingPasswordModalSidebar();
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
</div>