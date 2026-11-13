<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard Monitoring Keuangan Project')</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts Inter & Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0284c7',
                            600: '#0284c7',
                            700: '#0369a1',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at 15% 10%, rgba(30, 64, 175, 0.45) 0%, transparent 45%),
                        radial-gradient(circle at 85% 15%, rgba(14, 116, 144, 0.3) 0%, transparent 40%),
                        radial-gradient(circle at 50% 85%, rgba(29, 78, 216, 0.35) 0%, transparent 50%),
                        linear-gradient(150deg, #030712 0%, #061229 28%, #0a1f44 65%, #020510 100%) !important;
            background-attachment: fixed !important;
            color: #f1f5f9 !important;
            min-height: 100vh;
        }

        /* Luxury Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #060d1f; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e3a8a; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.3); }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #3b82f6; }

        /* Luxury Glass Cards */
        .bg-white {
            background: rgba(10, 22, 47, 0.88) !important;
            border-color: rgba(59, 130, 246, 0.22) !important;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
        .bg-white:hover {
            border-color: rgba(96, 165, 250, 0.38) !important;
        }

        /* High Contrast Headings and Titles */
        .text-slate-900,
        .text-slate-800 {
            color: #ffffff !important;
        }
        .text-slate-700 {
            color: #e2e8f0 !important;
        }
        .text-slate-600 {
            color: #cbd5e1 !important;
        }
        .text-slate-500,
        .text-slate-400 {
            color: #94a3b8 !important;
        }

        /* Borders in cards and sections */
        .border-slate-200,
        .border-slate-100,
        .divide-slate-100 {
            border-color: rgba(59, 130, 246, 0.18) !important;
        }
        .border-slate-300 {
            border-color: rgba(59, 130, 246, 0.32) !important;
        }

        /* Sub-containers & Header Bar */
        .bg-slate-50,
        .bg-slate-100 {
            background-color: rgba(6, 15, 34, 0.75) !important;
            border-color: rgba(59, 130, 246, 0.2) !important;
        }
        .bg-slate-200 {
            background-color: rgba(14, 29, 60, 0.85) !important;
        }
        .bg-slate-900 {
            background: linear-gradient(135deg, rgba(5, 12, 28, 0.98) 0%, rgba(10, 25, 54, 0.98) 50%, rgba(6, 15, 34, 0.98) 100%) !important;
            border-color: rgba(59, 130, 246, 0.25) !important;
        }
        .bg-slate-800 {
            background-color: rgba(9, 21, 46, 0.92) !important;
            border-color: rgba(59, 130, 246, 0.22) !important;
        }

        /* Form Inputs, Selects, and Textareas */
        input[type="text"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            background-color: #060e20 !important;
            color: #ffffff !important;
            border: 1px solid rgba(59, 130, 246, 0.35) !important;
        }
        input::placeholder,
        textarea::placeholder {
            color: #64748b !important;
        }
        input:focus,
        select:focus,
        textarea:focus {
            background-color: #08142a !important;
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.3) !important;
            outline: none !important;
        }
        select option {
            background-color: #081329 !important;
            color: #ffffff !important;
        }

        /* Buttons with White Background in Original Layout */
        button.bg-white,
        a.bg-white {
            background: rgba(15, 30, 62, 0.9) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
            color: #f1f5f9 !important;
        }
        button.bg-white:hover,
        a.bg-white:hover {
            background: rgba(29, 78, 216, 0.35) !important;
            border-color: rgba(96, 165, 250, 0.5) !important;
            color: #ffffff !important;
        }

        /* Status Badges & Accents - Luminous & High Contrast */
        .text-emerald-500,
        .text-emerald-600,
        .text-emerald-700,
        .text-emerald-800 {
            color: #34d399 !important;
        }
        .text-amber-500,
        .text-amber-600,
        .text-amber-700,
        .text-amber-800 {
            color: #fbbf24 !important;
        }
        .text-blue-500,
        .text-blue-600,
        .text-blue-700,
        .text-blue-800 {
            color: #60a5fa !important;
        }
        .text-rose-500,
        .text-rose-600,
        .text-rose-700,
        .text-rose-800 {
            color: #f87171 !important;
        }
        .text-purple-500,
        .text-purple-600,
        .text-purple-700,
        .text-purple-800 {
            color: #c084fc !important;
        }

        .bg-blue-50, .bg-blue-100, .bg-blue-200 {
            background-color: rgba(30, 58, 138, 0.45) !important;
            color: #93c5fd !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        .bg-amber-50, .bg-amber-100, .bg-amber-200 {
            background-color: rgba(180, 83, 9, 0.35) !important;
            color: #fde68a !important;
            border-color: rgba(245, 158, 11, 0.3) !important;
        }
        .bg-emerald-50, .bg-emerald-100, .bg-emerald-200 {
            background-color: rgba(6, 95, 70, 0.4) !important;
            color: #a7f3d0 !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        .bg-rose-50, .bg-rose-100, .bg-rose-200 {
            background-color: rgba(159, 18, 57, 0.4) !important;
            color: #fecdd3 !important;
            border-color: rgba(244, 63, 94, 0.3) !important;
        }
        .bg-purple-50, .bg-purple-100, .bg-purple-200 {
            background-color: rgba(88, 28, 135, 0.4) !important;
            color: #e9d5ff !important;
            border-color: rgba(168, 85, 247, 0.3) !important;
        }

        /* View Switcher Active State */
        #viewBtnExpenses.bg-white,
        #viewBtnKasbon.bg-white,
        #viewBtnInvestor.bg-white {
            background: linear-gradient(135deg, #2563eb, #4f46e5) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px 0 rgba(37, 99, 235, 0.45) !important;
            border-color: rgba(96, 165, 250, 0.4) !important;
        }

        /* Tables */
        table thead {
            background: rgba(4, 10, 24, 0.95) !important;
            color: #93c5fd !important;
            border-bottom: 1px solid rgba(59, 130, 246, 0.3) !important;
        }
        table thead th {
            color: #93c5fd !important;
        }
        table tbody tr {
            border-bottom: 1px solid rgba(59, 130, 246, 0.12) !important;
        }
        table tbody tr:hover {
            background-color: rgba(30, 58, 138, 0.28) !important;
        }
        table tbody tr[class*="bg-amber-"] {
            background-color: rgba(180, 83, 9, 0.22) !important;
        }
        table tbody tr[class*="bg-emerald-"] {
            background-color: rgba(6, 95, 70, 0.22) !important;
        }
        table tbody tr[class*="bg-blue-"] {
            background-color: rgba(30, 58, 138, 0.22) !important;
        }
        table tfoot {
            background: rgba(4, 10, 24, 0.95) !important;
            border-top: 2px solid rgba(59, 130, 246, 0.35) !important;
            color: #ffffff !important;
        }

        /* Kategori Biaya Cards Specific Dark Luxury Rules */
        #categoryListContainer > div {
            background-color: #060f24 !important;
            border: 1px solid rgba(59, 130, 246, 0.25) !important;
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.6) !important;
        }
        #categoryListContainer > div:hover {
            background-color: #0a1836 !important;
            border-color: rgba(96, 165, 250, 0.45) !important;
        }
        #categoryListContainer h3 {
            color: #ffffff !important;
        }
        #categoryListContainer p {
            color: #93c5fd !important;
        }

        /* Modals and Dialogs */
        .fixed.inset-0.z-50,
        .fixed.inset-0.z-\[55\],
        .fixed.inset-0.z-\[60\] {
            background-color: rgba(2, 6, 23, 0.78) !important;
            backdrop-filter: blur(8px) !important;
        }
        #categorySettingsModal > div,
        #categoryModal > div,
        #deleteCategoryModal > div,
        #confirmModal > div {
            background: #09152d !important;
            border: 1px solid rgba(59, 130, 246, 0.35) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 25px rgba(37, 99, 235, 0.2) !important;
            color: #f8fafc !important;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-12">
    @yield('content')
    @stack('scripts')
</body>
</html>
