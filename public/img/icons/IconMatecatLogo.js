const IconManage = ({width = '42px', height = '42px', style}) => {
  return (
    <svg
      width={width}
      height={height}
      style={style}
      xmlns="http://www.w3.org/2000/svg"
      xmlnsXlink="http://www.w3.org/1999/xlink"
      viewBox="0 0 42 42"
    >
      <defs>
        <path id="a" d="M0 -0.0002L21 -0.0002 21 21 0 21z" />
        <path id="c" d="M0 0.323L3.9997 0.323 3.9997 4.3226 0 4.3226z" />
        <path id="e" d="M0 0.1166L3.9997 0.1166 3.9997 4.1166 0 4.1166z" />
        <path id="g" d="M0 0.0004L3.9997 0.0004 3.9997 4.0004 0 4.0004z" />
      </defs>
      <g fill="none" fillRule="evenodd" stroke="none" strokeWidth="1">
        <path
          fill="#FFF"
          d="M22.5 9.777c0-.6-.4-1-1-1H9c-.6 0-1 .4-1 1s.4 1 1 1h12.5c.6 0 1-.4 1-1M9 3.678h14c.6 0 1-.4 1-1 0-.602-.4-1-1-1H9c-.6 0-1 .398-1 1 0 .6.4 1 1 1M9 15.678c-.6 0-1 .398-1 1 0 .6.4 1 1 1h6c.6 0 1-.4 1-1 0-.602-.4-1-1-1H9z"
          transform="translate(2 9)"
        />
        <g transform="translate(2 9) translate(17 2.677)">
          <mask id="b" fill="#fff">
            <use xlinkHref="#a" />
          </mask>
          <path
            fill="#FFF"
            d="M2 17.4l15-15L18.6 4l-15 15H2v-1.6zM1 21h3c.3 0 .5-.1.7-.3l16-16c.4-.4.4-1 0-1.4l-3-3c-.4-.4-1-.4-1.4 0l-16 16c-.2.2-.3.4-.3.7v3c0 .6.4 1 1 1z"
            mask="url(#b)"
          />
        </g>
        <g transform="translate(2 9) translate(0 .677)">
          <mask id="d" fill="#fff">
            <use xlinkHref="#c" />
          </mask>
          <path
            fill="#FFF"
            d="M1.035 4.323h1.93C3.538 4.323 4 3.86 4 3.289V1.358C4 .786 3.537.323 2.966.323H1.035C.464.323 0 .786 0 1.358v1.93c0 .572.464 1.035 1.035 1.035"
            mask="url(#d)"
          />
        </g>
        <g transform="translate(2 9) translate(0 7.677)">
          <mask id="f" fill="#fff">
            <use xlinkHref="#e" />
          </mask>
          <path
            fill="#FFF"
            d="M1.035 4.117h1.93C3.538 4.117 4 3.654 4 3.083V1.15C4 .58 3.537.117 2.966.117H1.035C.464.117 0 .58 0 1.15v1.932c0 .57.464 1.034 1.035 1.034"
            mask="url(#f)"
          />
        </g>
        <g transform="translate(2 9) translate(0 14.677)">
          <mask id="h" fill="#fff">
            <use xlinkHref="#g" />
          </mask>
          <path
            fill="#FFF"
            d="M1.035 4h1.93C3.538 4 4 3.536 4 2.965v-1.93C4 .462 3.537 0 2.966 0H1.035C.464 0 0 .463 0 1.034v1.931C0 3.536.464 4 1.035 4"
            mask="url(#h)"
          />
        </g>
      </g>
    </svg>
  )
}

export default IconManage
