<x-layouts.portal title="Home">
    <h1 class="text-2xl font-bold tracking-tight text-gray-950">
        Welcome, {{ auth('applicant')->user()->first_name }}
    </h1>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <h2 class="text-base font-semibold text-gray-950">Applicant Portal</h2>
        <p class="mt-1 text-sm text-gray-500">The layout works. Real pages come in the next steps.</p>
    </div>
</x-layouts.portal>