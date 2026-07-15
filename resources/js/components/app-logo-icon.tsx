import { ImgHTMLAttributes } from 'react';
import nimrLogo from '../../images/nimr-logo.png';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src={nimrLogo} alt="NIMR" {...props} className={`rounded-full object-contain ${props.className ?? ''}`} />;
}
