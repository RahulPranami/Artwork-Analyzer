/**
 * WP Artwork Analyzer - Admin Diagnostics Script
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const streamBtn = document.getElementById('wpa-run-stream-test');
        const geminiBtn = document.getElementById('wpa-run-gemini-test');
        const consoleEl = document.getElementById('wpa-test-console');
        const consoleBody = document.getElementById('wpa-console-body');
        const consoleStatus = document.getElementById('wpa-console-status');

        const config = window.wpaAdminConfig || {};

        function log(msg) {
            if (consoleBody) {
                consoleBody.textContent += msg + '\n';
                consoleBody.scrollTop = consoleBody.scrollHeight;
            }
        }

        function clearConsole(title) {
            if (consoleEl) consoleEl.style.display = 'block';
            if (consoleBody) consoleBody.textContent = '';
            if (consoleStatus) consoleStatus.textContent = title;
        }

        // 1. Streaming Latency Test
        if (streamBtn) {
            streamBtn.addEventListener('click', async function () {
                streamBtn.disabled = true;
                clearConsole('Running Progressive Streaming Test...');
                log('[1/4] Connecting to /wp-json/art-analyzer/v1/stream-test via ReadableStream...');

                const endpoint = (config.restUrl || '/wp-json/art-analyzer/v1/') + 'stream-test';
                const startTime = performance.now();
                let lastChunkTime = startTime;
                const chunkArrivalTimes = [];

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': config.nonce || ''
                        }
                    });

                    if (!response.ok) {
                        log(`✕ HTTP Error: ${response.status} ${response.statusText}`);
                        streamBtn.disabled = false;
                        return;
                    }

                    log('[2/4] HTTP 200 Received. Monitoring chunk delivery intervals...');

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder('utf-8');
                    let buffer = '';

                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;

                        const now = performance.now();
                        const interval = Math.round(now - lastChunkTime);
                        lastChunkTime = now;

                        const rawChunk = decoder.decode(value, { stream: true });
                        buffer += rawChunk;

                        const lines = buffer.split('\n\n');
                        buffer = lines.pop();

                        for (const item of lines) {
                            if (item.includes('event: text')) {
                                chunkArrivalTimes.push(interval);
                                const match = item.match(/"text":"(.*?)"/);
                                const text = match ? match[1] : item;
                                log(`   ➜ Chunk arrived [${interval}ms delta]: "${text}"`);
                            }
                        }
                    }

                    log('[3/4] Stream finished.');

                    // Analyze arrival timings
                    if (chunkArrivalTimes.length >= 3) {
                        // Check if chunks arrived clustered together at the end
                        const allInstant = chunkArrivalTimes.slice(1).every(t => t < 80);
                        if (allInstant) {
                            log('\n⚠️ BUFFERING DETECTED:');
                            log('All response chunks arrived simultaneously at the end of the request.');
                            log('This indicates your server (Apache/nginx/proxy/Hostinger) or PHP is buffering output.');
                            log('Resolution: Ensure X-Accel-Buffering: no is respected and zlib.output_compression is disabled.');
                            if (consoleStatus) consoleStatus.textContent = 'Failed (Buffered)';
                        } else {
                            log('\n✓ STREAMING VERIFIED:');
                            log('Chunks were delivered progressively over time. Live AI streaming is fully operational!');
                            if (consoleStatus) consoleStatus.textContent = 'Success (Progressive)';
                        }
                    } else {
                        log('✓ Stream complete.');
                        if (consoleStatus) consoleStatus.textContent = 'Completed';
                    }

                } catch (err) {
                    log(`✕ Stream error: ${err.message}`);
                    if (consoleStatus) consoleStatus.textContent = 'Error';
                } finally {
                    streamBtn.disabled = false;
                }
            });
        }

        // 2. Gemini Connection Test
        if (geminiBtn) {
            geminiBtn.addEventListener('click', async function () {
                geminiBtn.disabled = true;
                clearConsole('Testing Gemini API Connection...');
                log('[1/2] Sending test ping to Gemini API...');

                const endpoint = (config.restUrl || '/wp-json/art-analyzer/v1/') + 'gemini-test';

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': config.nonce || '',
                            'Content-Type': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        log(`[2/2] Success! Connection verified.`);
                        log(`   Model:   ${data.model}`);
                        log(`   Latency: ${data.latency} ms`);
                        log(`   Message: ${data.message}`);
                        log('\n✓ Gemini API is configured and responding normally.');
                        if (consoleStatus) consoleStatus.textContent = 'Connected (OK)';
                    } else {
                        log(`✕ Gemini Connection Failed:`);
                        log(`   Model:   ${data.model || 'Unknown'}`);
                        log(`   Error:   ${data.message}`);
                        log(`   Latency: ${data.latency || 0} ms`);
                        if (consoleStatus) consoleStatus.textContent = 'Failed';
                    }
                } catch (err) {
                    log(`✕ Request failed: ${err.message}`);
                    if (consoleStatus) consoleStatus.textContent = 'Error';
                } finally {
                    geminiBtn.disabled = false;
                }
            });
        }
    });
})();
