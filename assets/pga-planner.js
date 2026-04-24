(function ($) {
    const REST = PGA_CFG.rest;
    const NONCE = PGA_CFG.nonce;
    function buildPlan({ generators, global }) {
        const jobs = [];
        let totalGenerated = 0;
        let day = 0;

        // cópia mutável
        const gens = generators.map(g => ({
            ...g,
            queue: [...g.keywords]
        }));

        while (true) {
            let producedToday = false;

            for (const gen of gens) {
                if (!gen.enabled) continue;

                const limit = gen.perDay || 1;
                let used = 0;

                while (
                    used < limit &&
                    gen.queue.length > 0 &&
                    (!global.enabled || totalGenerated < global.total)
                ) {
                    jobs.push({
                        genId: gen.id,
                        keyword: gen.queue.shift(),
                        dayIndex: day,
                        order: totalGenerated
                    });

                    totalGenerated++;
                    used++;
                    producedToday = true;
                }
            }

            if (!producedToday) break;
            if (global.enabled && totalGenerated >= global.total) break;

            day++;
        }

        return jobs;
    }


    $('#pga_plan').on('click', async () => {
        const generators = collectGeneratorsFromDOM();
        const global = collectGlobalPlanFromDOM();

        const jobs = buildPlan({ generators, global });

        console.log('PLAN FINAL:', jobs);

        await executeJobsSequentially(jobs, generators);

        await saveAllState({ generators, global, jobs });
    });

    async function executeJobsSequentially(jobs, generators) {
        for (const job of jobs) {
            const gen = generators.find(g => g.id === job.genId);

            await generateSinglePost({
                keyword: job.keyword,
                prefs: gen
            });
        }
    }
    async function saveAllState({ generators, global, jobs }) {
        return fetchJSON(`${REST}/save`, {
            method: 'POST',
            body: JSON.stringify({
                global,
                generators,
                stats: {
                    total_jobs: jobs.length,
                    generated_at: Date.now()
                }
            })
        });
    }
})(jQuery);
