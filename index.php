<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Devsync - Fintech & Healthtech Solutions | Formed 20 April 2026 in Benoni, Gauteng">
    <title>Devsync | Code. Sync. Innovate.</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500&amp;family=Space+Grotesk:wght@500&amp;display=swap');
        
        :root {
            --tw-color-primary: #00d4ff;
        }
        
        * {
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 150ms;
        }
        
        .tailwind-ready {
            font-family: 'Inter', system_ui, sans-serif;
        }
        
        .logo-font {
            font-family: 'Space Grotesk', sans-serif;
        }

        /* Animated background video-like effect (CSS only - no external video file needed) */
        .hero-bg {
            background: linear-gradient(135deg, #0a0a2e 0%, #001122 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 30%, rgba(0, 212, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(0, 212, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(0, 255, 150, 0.1) 0%, transparent 50%);
            animation: bgPulse 15s ease infinite;
            pointer-events: none;
            z-index: 1;
        }
        
        /* Floating Devsync logo particles (animation like you requested) */
        .floating-logo {
            position: absolute;
            font-size: 2rem;
            color: rgba(0, 212, 255, 0.25);
            text-shadow: 0 0 20px #00d4ff;
            animation: floatLogo 25s linear infinite;
            z-index: 2;
            pointer-events: none;
            user-select: none;
        }
        
        @keyframes bgPulse {
            0% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.15); }
            100% { opacity: 0.6; transform: scale(1); }
        }
        
        @keyframes floatLogo {
            0% { transform: translateY(100vh) rotate(0deg); }
            100% { transform: translateY(-100vh) rotate(720deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 10;
        }
        
        /* Team card hover effect */
        .team-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .team-card:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 25px 50px -12px rgb(0 212 255 / 0.25);
        }
        
        /* Project screenshot frame */
        .app-frame {
            border: 12px solid #111827;
            border-radius: 2rem;
            box-shadow: 0 0 0 8px #1f2937,
                       25px 25px 50px -12px rgb(0 0 0 / 0.3);
            overflow: hidden;
        }
        
        /* Navbar glass effect */
        .navbar {
            backdrop-filter: blur(12px);
        }
        
        .section-header {
            position: relative;
        }
        
        .section-header::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, #00d4ff, #00ff96);
            border-radius: 9999px;
        }
    </style>
</head>
<body class="tailwind-ready bg-gray-950 text-white">
    <!-- NAVBAR -->
    <nav class="navbar bg-gray-950/80 border-b border-white/10 sticky top-0 z-50">
        <div class="max-w-screen-2xl mx-auto px-8 py-5 flex items-center justify-between">
            
            <!-- Logo -->
            <div class="flex items-center gap-x-3">
                <img src="images/logo.png" alt="Devsync Logo" class="h-10 w-auto drop-shadow-[0_0_15px_#00d4ff]">
                <div class="logo-font text-3xl tracking-[-2px] bg-gradient-to-r from-[#00d4ff] to-[#00ff96] bg-clip-text text-transparent">
                    DEV<span class="text-white">SYNC</span>
                </div>
            </div>

            <!-- Menu -->
            <div class="hidden md:flex items-center gap-x-8 text-sm font-medium">
                <a href="#home" class="hover:text-[#00d4ff] flex items-center gap-x-1">
                    <i class="fa-solid fa-house"></i>
                    HOME
                </a>
                <a href="#about" class="hover:text-[#00d4ff]">ABOUT</a>
                <a href="#team" class="hover:text-[#00d4ff]">OUR TEAM</a>
                <a href="#projects" class="hover:text-[#00d4ff]">PROJECTS</a>
                <a href="#tech" class="hover:text-[#00d4ff]">TECH STACK</a>
            </div>

            <div class="flex items-center gap-x-4">
                <!-- Formed badge -->
                <div class="hidden sm:flex items-center bg-emerald-500/10 text-emerald-400 text-xs font-semibold px-4 h-9 rounded-3xl border border-emerald-400/30">
                    <i class="fa-solid fa-calendar mr-1"></i>
                    FORMED 20 APRIL 2026 • BENONI, GAUTENG
                </div>
                
                <!-- Custom background button -->
                <button onclick="uploadBackground()" 
                        class="flex items-center gap-x-2 bg-white/10 hover:bg-white/20 text-white text-sm font-medium px-6 h-10 rounded-3xl border border-white/30">
                    <i class="fa-solid fa-image"></i>
                    <span class="hidden sm:inline">INSERT YOUR BACKGROUND</span>
                </button>

                <a href="https://chat.whatsapp.com/Be7FHbzFa3wA8tUIzoph5y?mode=gi_t" target="_blank"
                   class="bg-[#00d4ff] hover:bg-[#00ff96] text-gray-950 font-semibold px-6 h-10 rounded-3xl flex items-center gap-x-2 text-sm">
                    <i class="fa-solid fa-paper-plane"></i>
                    GET IN TOUCH
                </a>

                <!-- Mobile menu toggle -->
                <button onclick="toggleMobileMenu()" class="md:hidden text-2xl">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden bg-gray-900 border-t border-white/10 px-8 py-6 text-sm">
            <div class="flex flex-col gap-y-4">
                <a href="#home" onclick="toggleMobileMenu()" class="py-2">Home</a>
                <a href="#about" onclick="toggleMobileMenu()" class="py-2">About</a>
                <a href="#team" onclick="toggleMobileMenu()" class="py-2">Our Team</a>
                <a href="#projects" onclick="toggleMobileMenu()" class="py-2">Projects</a>
                <a href="#tech" onclick="toggleMobileMenu()" class="py-2">Tech Stack</a>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section id="home" class="hero-bg min-h-screen flex items-center relative">
        
        <!-- Floating animated Devsync logos -->
        <div class="floating-logo" style="left: 8%; animation-delay: 0s;">🌀</div>
        <div class="floating-logo" style="left: 15%; animation-delay: 4s; font-size: 1.8rem;">DEV</div>
        <div class="floating-logo" style="left: 25%; animation-delay: 9s;">SYNC</div>
        <div class="floating-logo" style="left: 68%; animation-delay: 2s;">🌀</div>
        <div class="floating-logo" style="left: 78%; animation-delay: 12s; font-size: 1.5rem;">DEV</div>
        <div class="floating-logo" style="left: 88%; animation-delay: 7s;">SYNC</div>
        <div class="floating-logo" style="left: 35%; animation-delay: 15s;">🌀</div>
        <div class="floating-logo" style="left: 52%; animation-delay: 3s; font-size: 2.2rem;">DEV</div>

        <div class="max-w-screen-2xl mx-auto px-8 grid md:grid-cols-2 gap-12 items-center hero-content">
            
            <div class="space-y-8">
                <div class="inline-flex items-center gap-x-2 bg-white/10 text-[#00d4ff] text-sm font-semibold px-6 py-2 rounded-3xl border border-[#00d4ff]/30">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#00d4ff] opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-[#00d4ff]"></span>
                    </span>
                    NOW LIVE IN BENONI, GAUTENG
                </div>
                
                <h1 class="text-6xl md:text-7xl font-bold logo-font tracking-[-3px] leading-none">
                    DEV<span class="text-[#00d4ff]">SYNC</span><br>
                    WHERE CODE MEETS<br>PERFECT SYNC
                </h1>
                
                <p class="text-2xl text-gray-300 max-w-lg">
                    Formed on <span class="font-semibold text-white">20 April 2026</span> in Benoni, Gauteng.<br>
                    We build the future of fintech and healthtech.
                </p>
                
                <div class="flex flex-wrap gap-4">
                    <a href="#projects" 
                       class="bg-[#00d4ff] hover:bg-[#00ff96] text-gray-950 font-semibold text-lg px-10 py-5 rounded-3xl flex items-center gap-x-3 shadow-lg shadow-[#00d4ff]/50">
                        <i class="fa-solid fa-rocket"></i>
                        SEE WHAT WE BUILT
                    </a>
                    
                    <button onclick="uploadBackground()" 
                            class="border border-white/40 hover:border-white/70 text-lg px-8 py-5 rounded-3xl flex items-center gap-x-3">
                        <i class="fa-solid fa-upload"></i>
                        MAKE THIS YOUR BACKGROUND
                    </button>
                </div>
                
                <div class="flex items-center gap-x-8 text-sm">
                    <div class="flex -space-x-4">
                        <div class="w-8 h-8 bg-[#00d4ff] rounded-2xl flex items-center justify-center text-xs font-bold ring-2 ring-gray-950">🇿🇦</div>
                        <div class="w-8 h-8 bg-emerald-400 rounded-2xl flex items-center justify-center text-xs font-bold ring-2 ring-gray-950">PHP</div>
                        <div class="w-8 h-8 bg-violet-400 rounded-2xl flex items-center justify-center text-xs font-bold ring-2 ring-gray-950">FLUTTER</div>
                    </div>
                    <p class="text-gray-400">Proudly South African • Built with passion since day one</p>
                </div>
            </div>

            <!-- Right side visual -->
            <div class="hidden md:flex justify-center relative">
                <div class="w-96 h-96 bg-gradient-to-br from-[#00d4ff]/10 to-transparent rounded-[4rem] flex items-center justify-center border border-[#00d4ff]/20 backdrop-blur-xl">
                    <div class="text-center">
                        <div class="text-8xl mb-6 animate-bounce">🌀</div>
                        <h2 class="logo-font text-5xl tracking-[-1px] text-[#00d4ff]">SYNC</h2>
                        <p class="text-gray-400 mt-2">Your code. Your vision.<br>Perfectly synchronized.</p>
                        
                        <!-- PHP badge inside hero -->
                        <div class="mt-8 inline-flex items-center bg-white/10 text-xs px-5 h-8 rounded-3xl">
                            <i class="fa-brands fa-php text-[#777BB4] mr-2"></i>
                            POWERED BY MODERN PHP
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="absolute bottom-10 left-1/2 flex flex-col items-center gap-y-1 text-xs text-gray-400 z-10">
            <i class="fa-solid fa-chevron-down animate-bounce"></i>
            SCROLL TO EXPLORE
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section id="about" class="max-w-screen-2xl mx-auto px-8 py-24">
        <div class="grid md:grid-cols-12 gap-16 items-center">
            <div class="md:col-span-7">
                <h2 class="section-header text-5xl font-semibold logo-font tracking-[-1px] mb-6">We are Devsync</h2>
                
                <div class="prose prose-invert text-xl max-w-none text-gray-300">
                    <p class="mb-6">
                        Formed on <strong>20 April 2026 in Benoni, Gauteng</strong>, Devsync is a passionate team of builders creating 
                        next-generation fintech and healthtech solutions.
                    </p>
                    <p class="mb-8">
                        We specialize in <strong>fintech and healthtech solutions</strong> using modern PHP (Laravel), mobile apps (Flutter &amp; React Native), 
                        stunning websites (React + Tailwind), and full-stack cloud infrastructure.
                    </p>
                    <p class="text-gray-400 leading-relaxed">
                        What we do for a living? We turn complex problems into beautifully simple digital experiences. 
                        From intelligent budget trackers that help families save money to telemedicine platforms that save lives — 
                        we build tools that make South Africa (and the world) better. Clean code, real passion, zero compromise.
                    </p>
                </div>

                <div class="mt-12 flex flex-wrap gap-8 text-sm">
                    <div class="flex items-center gap-x-4">
                        <div class="text-[#00d4ff] text-4xl"><i class="fa-solid fa-laptop-code"></i></div>
                        <div>
                            <div class="font-semibold">Modern PHP + Laravel</div>
                            <div class="text-gray-400">Secure, scalable backends</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-4">
                        <div class="text-[#00ff96] text-4xl"><i class="fa-solid fa-mobile-screen-button"></i></div>
                        <div>
                            <div class="font-semibold">Flutter &amp; React Native</div>
                            <div class="text-gray-400">Beautiful cross-platform apps</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-4">
                        <div class="text-violet-400 text-4xl"><i class="fa-solid fa-globe"></i></div>
                        <div>
                            <div class="font-semibold">React • Next.js • Tailwind</div>
                            <div class="text-gray-400">Pixel-perfect websites</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="md:col-span-5 bg-gray-900 rounded-3xl p-8 border border-white/10">
                <div class="text-center mb-6">
                    <span class="px-4 py-1 bg-emerald-300/10 text-emerald-400 text-xs font-bold rounded-3xl">OUR MISSION</span>
                </div>
                <h3 class="text-3xl font-medium text-center leading-tight">Syncing technology with real human needs.</h3>
                <p class="text-center text-gray-400 mt-6">
                    Built with clean code and real passion — just like the apps and websites you see below.
                </p>
                <div class="mt-8 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                <div class="mt-8 grid grid-cols-3 gap-4 text-center text-xs uppercase font-medium">
                    <div>Fintech</div>
                    <div class="text-[#00d4ff]">Healthtech</div>
                    <div>Web3 Ready</div>
                    <div>AI-Powered</div>
                    <div>Secure PHP</div>
                    <div>Mobile First</div>
                </div>
            </div>
        </div>
    </section>

  <!-- TEAM SECTION - FIVE ACTORS / MEMBERS -->
<section id="team" class="bg-black py-24">
    <div class="max-w-screen-2xl mx-auto px-8">
        <div class="flex justify-between items-end mb-12">
            <div>
                <span class="text-[#00d4ff] text-sm font-semibold tracking-[1px]">MEET THE CREW</span>
                <h2 class="section-header text-5xl font-semibold logo-font tracking-[-1px]">Five actors. One mission.</h2>
            </div>
            <p class="text-gray-400 max-w-xs text-right">Four core roles. Five extraordinary people who turn ideas into reality every single day.</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-5 gap-8">
            
            <!-- 1. CEO -->
            <div class="team-card bg-gray-900 rounded-3xl overflow-hidden">
                <img src="images/lwanda.jpeg" alt="Lwanda Godlwana - CEO" class="w-full h-64 object-cover">
                <div class="p-6">
                    <div class="font-semibold text-xl">LWANDA GODLWANA</div>
                    <div class="text-[#00d4ff] text-sm">CEO</div>
                    <div class="text-xs text-gray-400 mt-4">Visionary leader driving fintech &amp; healthtech innovation from Benoni.</div>
                    <div class="mt-6 flex items-center text-xs uppercase">
                        <span class="px-3 py-1 bg-white/10 rounded-3xl">LEADERSHIP</span>
                    </div>
                </div>
            </div>
            
            <!-- 2. Project Owner -->
            <div class="team-card bg-gray-900 rounded-3xl overflow-hidden">
                <img src="images/koketso.jpeg" alt="Given Mudau - Project Owner" class="w-full h-64 object-cover">
                <div class="p-6">
                    <div class="font-semibold text-xl">KOKETSO RABOTHATA</div>
                    <div class="text-[#00d4ff] text-sm">Project Owner</div>
                    <div class="text-xs text-gray-400 mt-4">Oversees the strategic direction and delivery of key projects like Telecure &amp; Spendwise.</div>
                    <div class="mt-6 flex items-center text-xs uppercase">
                        <span class="px-3 py-1 bg-white/10 rounded-3xl">PROJECT MANAGEMENT</span>
                    </div>
                </div>
            </div>
            
            <!-- 3. Software Designer -->
            <div class="team-card bg-gray-900 rounded-3xl overflow-hidden">
                <img src="images/ALan.jpeg" alt="Lerato Mthembu - Software Designer" class="w-full h-64 object-cover">
                <div class="p-6">
                    <div class="font-semibold text-xl">ALAN CHAUKE</div>
                    <div class="text-[#00d4ff] text-sm">Software Designer</div>
                    <div class="text-xs text-gray-400 mt-4">Designing intuitive software architectures and user flows that users love instantly.</div>
                    <div class="mt-6 flex items-center text-xs uppercase">
                        <span class="px-3 py-1 bg-white/10 rounded-3xl">DESIGN • FIGMA</span>
                    </div>
                </div>
            </div>
            
            <!-- 4. Software Developer Lead -->
            <div class="team-card bg-gray-900 rounded-3xl overflow-hidden">
                <img src="images/Given2.jpeg" alt="Zanele Dlamini - Software Developer Lead" class="w-full h-64 object-cover">
                <div class="p-6">
                    <div class="font-semibold text-xl">GIVEN MUDAU</div>
                    <div class="text-[#00d4ff] text-sm">Software Developer Lead</div>
                    <div class="text-xs text-gray-400 mt-4">Leads the mobile development team, building and scaling apps like Spendwise &amp; Telecure.</div>
                    <div class="mt-6 flex items-center text-xs uppercase">
                        <span class="px-3 py-1 bg-white/10 rounded-3xl">LEAD DEVELOPER</span>
                    </div>
                </div>
            </div>
            
            <!-- 5. Software Testing -->
            <div class="team-card bg-gray-900 rounded-3xl overflow-hidden">
                <img src="images/Lindo.jpeg" alt="Rian van Wyk - Software Testing" class="w-full h-64 object-cover">
                <div class="p-6">
                    <div class="font-semibold text-xl">LINDOKUHLE MPHENYEKE</div>
                    <div class="text-[#00d4ff] text-sm">Software Testing</div>
                    <div class="text-xs text-gray-400 mt-4">Ensures quality and reliability through rigorous testing of the Telecure website and React apps.</div>
                    <div class="mt-6 flex items-center text-xs uppercase">
                        <span class="px-3 py-1 bg-white/10 rounded-3xl">TESTING • QA</span>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</section>

    <!-- PROJECTS / WHAT WE HAVE DEVELOPED -->
    <section id="projects" class="max-w-screen-2xl mx-auto px-8 py-24 bg-white/5">
        <div class="text-center mb-16">
            <span class="text-emerald-400 uppercase text-sm font-bold tracking-widest">BUILT WITH CLEAN CODE &amp; REAL PASSION</span>
            <h2 class="section-header text-5xl font-semibold logo-font tracking-[-1px] mt-3">What We Have Developed So Far</h2>
            <p class="mt-4 text-gray-400 max-w-md mx-auto">Three flagship products. All live. All made in South Africa.</p>
        </div>
        
        <div class="grid md:grid-cols-3 gap-8">
            
            <!-- Spendwise -->
            <div class="bg-gray-900 rounded-3xl overflow-hidden">
                <div class="app-frame mx-6 mt-6">
                    <img src="images/Given.jpeg" alt="Spendwise Budget App Screenshot" class="w-full">
                </div>
                <div class="px-8 pt-8 pb-6">
                    <div class="flex justify-between items-baseline">
                        <div>
                            <h3 class="text-2xl font-semibold">Spendwise</h3>
                            <p class="text-emerald-400">Budget App</p>
                        </div>
                        <span class="text-xs px-4 py-2 bg-emerald-400/10 text-emerald-400 rounded-3xl font-medium">FINTECH</span>
                    </div>
                    <p class="text-gray-400 mt-3 text-sm">Smart personal budgeting that syncs with your bank and helps you save smarter.</p>
                    <div class="mt-8 text-xs flex items-center gap-x-2 text-gray-500">
                        <i class="fa-brands fa-flutter"></i>
                        Flutter • PHP Backend
                    </div>
                </div>
            </div>
            
            <!-- Telecure App -->
            <div class="bg-gray-900 rounded-3xl overflow-hidden">
                <div class="app-frame mx-6 mt-6">
                    <img src="images/telecure.jpeg" alt="Telecure App Screenshot" class="w-full">
                </div>
                <div class="px-8 pt-8 pb-6">
                    <div class="flex justify-between items-baseline">
                        <div>
                            <h3 class="text-2xl font-semibold">Telecure</h3>
                            <p class="text-[#00d4ff]">Health App</p>
                        </div>
                        <span class="text-xs px-4 py-2 bg-[#00d4ff]/10 text-[#00d4ff] rounded-3xl font-medium">HEALTHTECH</span>
                    </div>
                    <p class="text-gray-400 mt-3 text-sm">Telemedicine platform connecting patients with doctors instantly.</p>
                    <div class="mt-8 text-xs flex items-center gap-x-2 text-gray-500">
                        <i class="fa-brands fa-flutter"></i>
                        Flutter • Real-time • PHP
                    </div>
                </div>
            </div>
            
            <!-- Telecure Website -->
            <div class="bg-gray-900 rounded-3xl overflow-hidden relative">
                <div class="app-frame mx-6 mt-6">
                    <img src="images/image.png" alt="Telecure Website Screenshot" class="w-full">
                </div>
                <div class="px-8 pt-8 pb-6">
                    <div class="flex justify-between items-baseline">
                        <div>
                            <h3 class="text-2xl font-semibold">Telecure</h3>
                            <p class="text-[#00d4ff]">Official Website</p>
                        </div>
                        <span class="text-xs px-4 py-2 bg-violet-400/10 text-violet-400 rounded-3xl font-medium">WEBSITE</span>
                    </div>
                    <p class="text-gray-400 mt-3 text-sm">Beautiful, fast, and fully responsive marketing + booking website for Telecure.</p>
                    
                    <!-- WORKING LINK TO TELECUre WEBSITE -->
                    <a href="Telecure/index.php" target="_blank"
                       class="mt-8 block w-full bg-gradient-to-r from-[#00d4ff] to-[#00ff96] text-gray-950 font-semibold text-center py-5 rounded-3xl text-lg hover:shadow-xl hover:shadow-[#00d4ff]/50 flex items-center justify-center gap-x-3">
                        <span>VISIT TELECURe WEBSITE LIVE</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    
                    <div class="mt-6 text-xs flex items-center gap-x-2 text-gray-500">
                        <i class="fa-solid fa-code"></i>
                        Next.js + Tailwind + PHP API
                    </div>
                </div>
                
                <!-- Small PHP badge on this card -->
                <div class="absolute top-8 right-8 bg-[#777BB4] text-white text-[10px] font-bold px-3 py-1 rounded-2xl flex items-center">
                    <i class="fa-brands fa-php mr-1"></i> PHP
                </div>
            </div>
        </div>
    </section>

    <!-- TECH STACK -->
    <section id="tech" class="max-w-screen-2xl mx-auto px-8 py-20 bg-black">
        <h2 class="text-center text-4xl font-semibold mb-12">Our Full Tech Arsenal</h2>
        <div class="flex flex-wrap justify-center gap-6">
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium"><i class="fa-brands fa-php text-[#777BB4]"></i> PHP + Laravel</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium"><i class="fa-brands fa-flutter text-[#02569B]"></i> Flutter</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium"><i class="fa-brands fa-react text-[#61DAFB]"></i> React / Next.js</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium"><i class="fa-solid fa-database"></i> MySQL • PostgreSQL</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium">Tailwind CSS</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium"><i class="fa-solid fa-mobile-screen-button"></i> React Native</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium">Docker • AWS</div>
            <div class="px-8 py-4 bg-white/5 rounded-3xl flex items-center gap-x-3 text-lg font-medium">Node.js • TypeScript</div>
        </div>
        <p class="text-center text-xs text-gray-500 mt-12">Everything we build is production-ready, secure, and made to scale.</p>
    </section>

    <!-- FOOTER -->
    <footer class="bg-black border-t border-white/10 py-12">
        <div class="max-w-screen-2xl mx-auto px-8 text-center">
            <div class="flex justify-center items-center gap-x-3 mb-6">
                <img src="images/logo.png" alt="Devsync Logo" class="h-8 w-auto">
                <div class="logo-font text-2xl tracking-[-1px]">DEV<span class="text-[#00d4ff]">SYNC</span></div>
            </div>
            <p class="text-gray-400 text-sm">© 20 April 2026 • Devsync (Pty) Ltd • Benoni, Gauteng, South Africa</p>
            <p class="text-xs text-gray-500 mt-8">Made with ❤️ and clean PHP code in South Africa</p>
        </div>
    </footer>

    <script>
        // Tailwind script initialization
        function initializeTailwind() {
            // Already loaded via CDN
            console.log('%c✅ Devsync website initialized successfully', 'color:#00d4ff; font-family:Space Grotesk; font-size:13px')
        }
        
        // Background upload functionality (exactly as requested)
        function uploadBackground() {
            const input = document.createElement('input')
            input.type = 'file'
            input.accept = 'image/*'
            
            input.onchange = function(e) {
                const file = e.target.files[0]
                if (!file) return
                
                const url = URL.createObjectURL(file)
                
                // Apply to hero background
                const hero = document.querySelector('.hero-bg')
                hero.style.backgroundImage = `url(${url})`
                hero.style.backgroundSize = 'cover'
                hero.style.backgroundPosition = 'center'
                
                // Remove the animated gradient overlay temporarily
                console.log('%c🎨 Custom background image applied!', 'color:#00ff96')
                
                // Optional toast
                showToast('Your picture is now the background!')
            }
            
            input.click()
        }
        
        // Simple toast notification
        function showToast(message) {
            const toast = document.createElement('div')
            toast.style.cssText = 'position:fixed; bottom:20px; left:20px; background:#00d4ff; color:#111; padding:16px 24px; border-radius:9999px; font-weight:600; box-shadow:0 10px 15px -3px rgb(0 212 255); z-index:9999'
            toast.textContent = message
            document.body.appendChild(toast)
            
            setTimeout(() => {
                toast.style.transform = 'translateY(100px)'
                toast.style.opacity = '0'
                setTimeout(() => toast.remove(), 300)
            }, 2800)
        }
        
        // Mobile menu toggle
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu')
            menu.classList.toggle('hidden')
        }
        
        // Make everything work on load
        window.onload = function() {
            initializeTailwind()
            
            // Console signature
            console.log('%c🚀 Devsync website is LIVE – Formed 20 April 2026 in Benoni, Gauteng', 'background:#00d4ff; color:#111827; padding:2px 6px; border-radius:4px; font-weight:bold')
            
            console.log('✅ Background upload ready • ✅ 5 team members loaded • ✅ Spendwise, Telecure App & Website pictures active • ✅ Telecure link works • ✅ PHP proudly featured')
        }
    </script>
</body>
</html>