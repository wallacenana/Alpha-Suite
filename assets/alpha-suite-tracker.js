(function () {

    function getPostId() {
        if (typeof PI_TRACKER === 'undefined') return 0;
        return PI_TRACKER.post_id || 0;
    }

    function getSession() {
        let session = localStorage.getItem("pi_session_id");

        if (!session) {
            session = crypto.randomUUID();
            localStorage.setItem("pi_session_id", session);
        }

        return session;
    }

    function sendView() {
        const postId = getPostId();
        if (!postId) return;

        fetch(`${PI_TRACKER.endpoint}/tracker`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                post_id: postId,
                session: getSession()
            })
        });
    }

    function sendDuration(seconds) {
        const postId = getPostId();
        if (!postId) return;

        const data = JSON.stringify({
            post_id: postId,
            duration: seconds,
            session: getSession()
        });

        navigator.sendBeacon(
            PI_TRACKER.endpoint + '/tracker/duration',
            new Blob([data], { type: 'application/json' })
        );
    }

    document.addEventListener("DOMContentLoaded", function () {

        sendView();

        let start = Date.now();
        let activeTime = 0;
        let isActive = true;

        // 🧠 detecta troca de aba
        document.addEventListener("visibilitychange", () => {

            if (document.hidden) {
                // pausa
                activeTime += Date.now() - start;
                isActive = false;
            } else {
                // volta
                start = Date.now();
                isActive = true;
            }

        });

        window.addEventListener("beforeunload", function () {

            if (isActive) {
                activeTime += Date.now() - start;
            }

            const seconds = Math.floor(activeTime / 1000);

            if (seconds > 5) {
                sendDuration(seconds);
            }

        });

        console.log("Alpha Suite: Tracker iniciado");

    });

})();