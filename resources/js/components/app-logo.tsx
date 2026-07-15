import nimrLogo from '../../images/nimr-logo.png';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-12 shrink-0 items-center justify-center rounded-full bg-white group-data-[collapsible=icon]:size-8">
                <img src={nimrLogo} alt="NIMR" className="size-11 rounded-full object-contain group-data-[collapsible=icon]:size-7" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-base group-data-[collapsible=icon]:hidden">
                <span className="mb-0.5 truncate font-serif leading-none font-semibold">TMSC</span>
            </div>
        </>
    );
}
