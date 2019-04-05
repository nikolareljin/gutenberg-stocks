import React from "react";

const getViewBox = name => {
	switch ( name ) {
		case "phone":
			return "0 0 32 33";
			break;
		case "message":
			return "0 0 38 34";
			break;
		case "envelope":
			return "0 0 40 26";
			break;
		case "trash":
			return "0 0 13.5 17.5";
			break;
		case "wifi":
			return "0 0 12 9";
			break;
		case "stocks":
		case "test":
			return "0 0 15 15";
			// 			return "0 0 386.651 386.651";
			break;
		default:
			return "0 0 32 32";
			break;
	}
};

const getPath = ( name, props ) => {
	switch ( name ) {
		case "phone":
			return (
				<path
					{ ...props }
					d="M 2.56635 12.9241C 0.708307 9.55549 0 6.83983 0 5.00558C 0 3.17134 0.450658 2.64526 0.907953 2.22025C 1.36525 1.79524 3.42732 0.523908 3.77867 0.286808C 4.13002 0.0497085 5.47099 -0.41107 6.31193 0.798636C 7.15287 2.00834 8.73646 4.43718 9.82825 6.05069C 11.5415 8.33611 10.1766 9.33937 9.73572 9.94069C 8.92447 11.0472 8.45734 11.3201 8.45734 12.6788C 8.45734 14.0375 12.2545 17.8976 13.1625 18.8487C 14.0635 19.7926 17.8471 23.1094 19.0195 23.2868C 20.2002 23.4654 21.7807 22.2154 22.1168 21.8985C 23.8263 20.586 24.7912 21.581 25.5787 22.0136C 26.3661 22.4461 29.9239 24.663 31.0264 25.4103C 32.0641 26.1576 31.9992 27.3125 31.9992 27.3125C 31.9992 27.3125 29.859 30.7092 29.5996 31.1168C 29.2753 31.5924 28.4971 32 26.746 32C 24.995 32 23.1241 31.6802 18.6777 29.2349C 15.0396 27.234 11.5714 24.1009 9.75551 22.2666C 7.87475 20.4324 4.68876 16.772 2.56635 12.9241Z"
				/>
			);
		case "message":
			return (
				<path
					{ ...props }
					d="M19.0675 0C8.5371 0 0 7.092 0 15.8401c0 5.5527 3.4417 10.4345 8.6471 13.2632.0083.0091.0221.0182.0434.0277.8433.3798.41 1.6817-.5651 2.8751-.742.9083-2.1668 1.8444-1.6251 1.8444.8667 0 2.7535-.4018 3.94-1.0597 1.7586-.9752 2.9897-1.8141 4.5758-1.469l-.0013-.0035a22.867 22.867 0 0 0 4.0527.3619c10.5309 0 19.0676-7.0916 19.0676-15.8401C38.1351 7.0921 29.5984 0 19.0675 0z"
				/>
			);
		case "envelope":
			return (
				<path
					{ ...props }
					d="M 22.8196 15.7456C 22.0005 16.5862 20.9038 17.0508 19.7317 17.0542C 18.5632 17.0444 17.46 16.5994 16.6362 15.7646L 1.09009 0L 38.1626 0L 22.8196 15.7456ZM 0 1.06567L 11.2952 12.5818L 0 24.1392L 0 1.06567ZM 27.9519 12.623L 39.2878 24.1233L 39.2878 1.06567L 27.9519 12.623ZM 23.7844 16.9265L 27.0105 13.6885L 38.1833 25.082L 1.10547 25.082L 12.4006 13.6885L 15.6772 16.9512C 16.7539 18.0415 18.1877 18.6418 19.7185 18.6418L 19.7363 18.6418C 21.2734 18.6375 22.7109 18.0281 23.7844 16.9265Z"
				/>
			);
		case "trash":
			return (
				<path
					{ ...props }
					d="M 8.18183 4.36365L 9.27274 4.36365L 9.27274 14.7273L 8.18183 14.7273L 8.18183 4.36365ZM 6.00001 4.36365L 7.09092 4.36365L 7.09092 14.7273L 6.00001 14.7273L 6.00001 4.36365ZM 3.81819 4.36365L 4.9091 4.36365L 4.9091 14.7273L 3.81819 14.7273L 3.81819 4.36365ZM 1.67946 2.72729L 2.72509 16.3216L 10.3636 16.3637L 11.4103 2.72729L 1.67946 2.72729ZM 7.512 1.08873L 5.56309 1.09091C 5.54127 1.10455 5.454 1.22236 5.454 1.41818L 5.454 1.63638L 7.63582 1.63638L 7.63582 1.41818C 7.63582 1.22236 7.54855 1.10455 7.512 1.08873ZM 5.56309 0L 7.52673 0C 8.19928 0 8.72673 0.622909 8.72673 1.41818L 8.72673 1.63638L 12.5885 1.63638L 13.0909 1.63638L 13.0909 2.72729L 12.5046 2.72729L 11.4524 16.4056C 11.454 16.9653 10.9642 17.4546 10.3631 17.4546L 2.72676 17.4546C 2.12511 17.4546 1.63585 16.9653 1.63585 16.3637L 0.585305 2.72729L 0 2.72729L 0 1.63638L 0.501262 1.63638L 4.36309 1.63638L 4.36309 1.41818C 4.36309 0.622909 4.89054 0 5.56309 0Z"
				/>
			);
		case "wifi":
			return (
				<path
					{ ...props }
					d="M 5.99609 1.51367C 7.93604 1.51367 9.7041 2.22479 11.0596 3.39136L 12 2.35657C 10.3945 0.969788 8.29785 0 5.99561 0C 3.69775 0 1.60352 0.967163 0 2.35022L 0.938965 3.38574C 2.29395 2.22217 4.05908 1.51367 5.99609 1.51367ZM 5.91553 5.9726C 5.13672 5.9726 4.42383 6.28912 3.86963 6.74567L 5.91553 9L 7.96338 6.74792C 7.40918 6.29028 6.69531 5.9726 5.91553 5.9726ZM 9.01318 5.61206C 8.19385 4.92072 7.13184 4.5 5.96875 4.5C 4.80811 4.5 3.74756 4.91882 2.9292 5.60907L 1.93457 4.5127C 3.01758 3.58954 4.42578 2.98633 5.96924 2.98633C 7.51562 2.98633 8.92578 3.59143 10.0088 4.51721L 9.01318 5.61206Z"
				/>
			);
		case "stocks":
			return(
				<path
					{...props}
					d="M342.367,135.781c-2.674-1.367-5.889-1.122-8.324,0.635l-138.556,99.968l-89.233-83.725
		c-3.032-2.844-7.736-2.892-10.826-0.112l-74.395,66.959c-1.685,1.518-2.648,3.679-2.648,5.946v91.451c0,4.418,3.582,8,8,8h312.339
		c4.418,0,8-3.582,8-8v-174C346.724,139.899,345.041,137.149,342.367,135.781z M53.507,308.903H34.385v-79.889l19.122-17.211
		V308.903z M88.045,308.903H69.507v-111.5l18.538-16.685V308.903z M122.582,308.903h-18.538V172.526l18.538,17.393V308.903z
		 M157.12,308.903h-18.538V204.931l18.538,17.394V308.903z M192.015,308.903H173.12v-71.565l16.227,15.226
		c0.791,0.741,1.702,1.288,2.667,1.65V308.903z M226.91,308.903h-18.896v-61.828l18.896-13.634V308.903z M261.806,308.903H242.91
		v-87.006l18.895-13.633V308.903z M296.701,308.903h-18.896V196.72l18.896-13.634V308.903z M330.724,308.903h-18.022v-137.36
		l18.022-13.003V308.903z" />
			);
		case "test":
			return(
				<path {...props}
							d="m 20.712589,5.823271 c -0.528802,-0.21711 -0.891374,0.51682 -1.317602,0.80648 -2.495938,2.3101305 -4.991876,4.620259 -7.487814,6.930389 -1.783164,-2.14629 -3.5663288,-4.2925785 -5.3494938,-6.438869 -0.537836,-0.49837 -0.924755,0.40505 -1.315916,0.7614005 -1.264354,1.45982 -2.528706,2.9196385 -3.793059,4.3794685 -0.292915,0.59485 -0.105764,1.36182 -0.158747,2.03991 0,1.8168 0,3.6336 0,5.4504 0.0793,0.86443 0.853108,0.55943 1.320764,0.61524 5.961157,0 11.9223148,0 17.8834708,0 0.673851,-0.10173 0.436091,-1.09439 0.479598,-1.69431 0,-4.1008 0,-8.20159 0,-12.302389 0.0018,-0.22652 -0.103189,-0.44662 -0.261201,-0.54772 z M 3.3955112,19.13721 c -0.382119,0 -0.764239,0 -1.146358,0 0,-2.04795 0,-4.0959 0,-6.14386 0.382119,-0.4412 0.764239,-0.88241 1.146358,-1.32361 0,2.48916 0,4.97831 0,7.46747 z m 2.070544,0 c -0.37045,0 -0.740899,0 -1.111348,0 0,-2.8583 0,-5.7166 0,-8.5749 0.370449,-0.42772 0.740898,-0.8554385 1.111348,-1.2831585 0,3.2860185 0,6.5720385 0,9.8580585 z m 2.070484,0 c -0.37045,0 -0.740899,0 -1.111349,0 0,-3.49602 0,-6.99204 0,-10.4880685 0.37045,0.445869 0.740899,0.89174 1.111349,1.3376093 0,3.0501492 0,6.1003092 0,9.1504592 z m 2.070543,0 c -0.370449,0 -0.740899,0 -1.111348,0 0,-2.66532 0,-5.33064 0,-7.99596 0.370449,0.44589 0.740899,0.89179 1.111348,1.33769 0,2.21942 0,4.43885 0,6.65827 z m 2.0919458,0 c -0.377583,0 -0.755167,0 -1.13275,0 0,-1.83457 0,-3.66913 0,-5.5037 0.372716,0.45667 0.789582,0.93678 1.13269,1.36978 4e-5,1.37795 -6e-5,2.7561 6e-5,4.13392 z m 2.091946,0 c -0.377604,0 -0.755207,0 -1.132811,0 0,-1.58496 0,-3.16992 0,-4.75488 0.377604,-0.3495 0.755207,-0.69901 1.132811,-1.04852 0,1.93447 0,3.86893 0,5.8034 z m 2.092005,0 c -0.377603,0 -0.755206,0 -1.13281,0 0,-2.2304 0,-4.46079 0,-6.69119 0.377583,-0.34948 0.755167,-0.69896 1.13275,-1.04845 4.1e-5,2.57986 -6e-5,5.15991 6e-5,7.73964 z m 2.091946,0 c -0.377604,0 -0.755207,0 -1.13281,0 0,-2.87581 0,-5.75162 0,-8.62743 0.377603,-0.3495 0.755206,-0.6990095 1.13281,-1.0485185 0,3.2253185 0,6.4506385 0,9.6759485 z m 2.03967,0 c -0.360138,0 -0.720277,0 -1.080414,0 0,-3.52122 0,-7.04244 0,-10.5636585 0.360137,-0.33333 0.720276,-0.66667 1.080414,-1.0000005 0,3.854549 0,7.709109 0,11.563659 z" />
			);
		default:
			return <path />;
	}
};

const SVGIcon = ( {
										name = "",
										style = {},
										fill = "#000",
										viewBox = "",
										width = "100%",
										className = "",
										height = "100%"
									} ) => (
	<svg
		width={ width }
		style={ style }
		height={ height }
		className={ className }
		xmlns="http://www.w3.org/2000/svg"
		viewBox={ viewBox || getViewBox( name ) }
		xmlnsXlink="http://www.w3.org/1999/xlink"
	>
		{ getPath( name, { fill } ) }
	</svg>
);

export default SVGIcon;
