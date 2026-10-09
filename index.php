<!DOCTYPE html>
<html lang="en">

<?php include("php/includes/head.php") ?>

<body class="font-sans antialiased text-white bg-brand-dark min-h-screen">

    <!-- Main Hero Container -->
    <section class="relative min-h-screen w-full flex flex-col justify-between overflow-hidden">

        <!-- Background Image -->
        <img
            src="https://images.pexels.com/photos/17615704/pexels-photo-17615704.jpeg"
            alt="Paxton University Campus"
            class="absolute inset-0 w-full h-full object-cover object-center filter brightness-[0.5]" />

        <!-- Brand Dark Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-b from-brand-black/80 via-brand-black/40 to-brand-black"></div>

        <!-- Center SVG Graphic Overlay -->
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-6">
            <svg class="w-full max-w-xl h-auto opacity-30 text-brand-accent" viewBox="0 0 500 500" fill="none" stroke="currentColor" stroke-width="1.2">
                <path d="M 150,50 L 350,50 A 100,100 0 0,1 450,150 L 450,350 A 100,100 0 0,1 350,450 L 150,450 A 100,100 0 0,1 50,350 L 50,150 A 100,100 0 0,1 150,50 Z" />
                <path d="M 200,100 L 400,100 A 80,80 0 0,1 480,180 L 480,380 A 80,80 0 0,1 400,460 L 200,460 A 80,80 0 0,1 120,380 L 120,180 A 80,80 0 0,1 200,100 Z" class="opacity-60" />
            </svg>
        </div>

        <!-- Top Navigation Bar -->
        <header class="relative z-10 flex items-center justify-between px-6 md:px-12 py-6">
            <!-- Logo & Text Container -->
            <a href="#" class="flex items-center space-x-3 group">
                <!-- Logo Icon -->
                <div class="w-10 h-10 rounded-md border border-brand-accent/30 backdrop-blur-md bg-brand-primary/40 flex items-center justify-center shadow-lg group-hover:border-brand-accent transition">
                    <svg class="w-5 h-5 text-brand-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                </div>
                <!-- Logo Text -->
                <span class="text-base font-medium tracking-widest text-brand-accent uppercase group-hover:text-white transition">
                    S.S.I.S
                </span>
            </a>
        </header>

        <!-- Bottom Content Area -->
        <main class="relative z-10 px-6 md:px-12 pb-12 pt-20 mt-auto">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row lg:items-end justify-between gap-8">

                <!-- Main Heading -->
                <div class="max-w-2xl">
                    <h1 class="text-5xl sm:text-7xl lg:text-8xl font-medium tracking-tight text-white leading-[1.05]">
                        Paxton <br />
                        <span class="font-normal text-brand-accent">University</span>
                    </h1>
                </div>

                <!-- Right Side: Description + CTA Button -->
                <div class="flex flex-col items-start max-w-sm space-y-6">
                    <p class="text-sm sm:text-base text-brand-accent font-light leading-relaxed">
                        Empowering future leaders through world-class education, innovative learning, and limitless opportunities.
                    </p>

                    <!-- Apply Now Button -->
                    <div class="flex flex-wrap gap-3">
                    <a href="application.php" class="inline-flex items-center justify-center border border-brand-accent text-brand-accent font-semibold px-8 py-3.5 rounded-full hover:bg-brand-accent hover:text-brand-dark transition text-sm">Apply Now</a>
                    <a href="login.php" class="inline-flex items-center justify-center bg-brand-accent text-brand-dark font-semibold px-8 py-3.5 rounded-full hover:bg-white transition shadow-lg text-sm">
                        Login Now
                    </a>
                    </div>
                </div>

            </div>
        </main>

    </section>

    <script>
        $("#redirectToLoginBtn").on("click", () => {
            window.location.href = "login.php";
        });
    </script>

</body>

</html>
