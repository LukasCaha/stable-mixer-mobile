async function bridgeCall(method, params = {}) {
    const response = await fetch('/_native/api/call', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    });

    return response.json();
}

export function speak(text) {
    return bridgeCall('Speech.Speak', { text });
}

export function stop() {
    return bridgeCall('Speech.Stop', {});
}
