<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>EventTransport Test</title>
    <style>
        body { font-family: sans-serif; padding:20px; }
        #log { white-space:pre; background:#f5f5f5; padding:10px; border:1px solid #ccc; height:300px; overflow-y:auto; }
        .tick { color:#0077cc; }
        .done { color:#990000; }
    </style>

    <script src="plugin/EventTransport/assets/eventtransportclient.js"></script>
</head>
<body>

<h1>EventTransport Streaming Test</h1>

<button id="startBtn">Start Stream</button>

<h2>Log</h2>
<div id="log"></div>

<script>
function log(msg, cls = "") {
    const el = document.createElement("div");
    if (cls) el.className = cls;
    el.textContent = msg;
    document.getElementById("log").appendChild(el);
    document.getElementById("log").scrollTop = 999999;
}

document.getElementById("startBtn").addEventListener("click", async () => {
    log("Opening SSE stream using EventTransportClient ...");

    const client = new EventTransportClient({
        endpoint: "sse.php",   // oder /weihnachtsmann.blub
        transport: "sse",      // explizit SSE
        events: ["tick"]       // alle Custom-Events ANGEBEN!
    });

    await client.connect((event, data) => {
        // HIER kommen JETZT wirklich ALLE Events an:
        // "tick", "done", "message", "tool.call", "whatever"
        switch (event) {
            case "tick":
                if (typeof data === "string") {
                    try { data = JSON.parse(data); } catch {}
                }
                log("[tick] index=" + data.index + " time=" + data.time, "tick");
                break;

            case "done":
                if (typeof data === "string") {
                    try { data = JSON.parse(data); } catch {}
                }
                log("DONE received! " + JSON.stringify(data), "done");
                client.close();
                break;

            case "message":
                log("MESSAGE: " + JSON.stringify(data));
                break;

            case "error":
                log("STREAM ERROR: " + data, "done");
                break;

            default:
                // alle anderen custom events
                log("EVENT [" + event + "]: " + JSON.stringify(data));
        }
    });

    // Für dein sse.php NICHT nötig:
    // client.request({ action: "start" });
});
</script>

</body>
</html>
