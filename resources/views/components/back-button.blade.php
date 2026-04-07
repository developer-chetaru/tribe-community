<a href="{{ url()->previous() }}" 
   class="bg-white px-5 py-2 rounded-sm shadow hover:bg-gray-200 transition">
   {{ $slot ?: 'Back' }}
</a>
