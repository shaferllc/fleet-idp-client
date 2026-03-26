@if (\Fleet\IdpClient\Services\FleetSocialLoginPolicy::isDebugPanelEnabled())
    @php
        $fleetIdpDebug = \Fleet\IdpClient\Services\FleetSocialLoginPolicy::debugPanelPayload();
    @endphp
    <div
        class="pointer-events-auto fixed bottom-0 left-0 right-0 z-[200] max-h-[40vh] overflow-auto border-t-2 border-amber-500 bg-amber-50/95 px-3 py-2 text-left shadow-[0_-4px_20px_rgba(0,0,0,0.12)] backdrop-blur-sm"
        role="complementary"
        aria-label="Fleet IdP debug"
        data-fleet-idp-debug-panel
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <p class="shrink-0 text-xs font-bold uppercase tracking-wide text-amber-900">
                Fleet IdP debug
                <span class="font-mono font-normal normal-case text-amber-800">(env or Fleet Auth “Satellite policy debug panel”)</span>
            </p>
            <pre class="min-w-0 flex-1 overflow-x-auto whitespace-pre-wrap break-all font-mono text-[11px] leading-snug text-amber-950">{{ json_encode($fleetIdpDebug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
@endif
