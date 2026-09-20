/* ============================================================
 * zhiji 淇锛歱io_sdk4.js 渚濊禆 DOMContentLoaded 鍒濆鍖?PIXI app锛? * 浣嗙湅鏉垮鑴氭湰涓哄欢杩熸敞鍏ワ紙window.load 鍚庯級锛岃浜嬩欢宸茶Е鍙戣繃锛? * 瀵艰嚧 app 姘镐笉涓?undefined 鈫?妯″瀷鍔犺浇鎴愬姛鍗翠笉娓叉煋銆? * 鍦?pio_sdk4 涔嬪悗鎵嬪姩琛ヨЕ鍙戯紝骞剁Щ闄ゅ叾鐢熸垚鐨勬棤 canvas 绌哄３瀹瑰櫒銆? * ============================================================ */
if (typeof app === 'undefined' && typeof _pio_initialize_pixi === 'function') {
	_pio_initialize_pixi();
	document.querySelectorAll('.pio-container').forEach(function (c) {
		if (!c.querySelector('#pio')) { c.parentNode && c.parentNode.removeChild(c); }
	});
}

var 寮曟祦 = [
  "https://space.bilibili.com/672328094",
  "https://www.bilibili.com/video/BV1FZ4y1F7HH",
  "https://www.bilibili.com/video/BV1FX4y1g7u8",
  "https://www.bilibili.com/video/BV1aK4y1P7Cg",
  "https://www.bilibili.com/video/BV17A411V7Uh",
  "https://www.bilibili.com/video/BV1JV411b7Pc",
  "https://www.bilibili.com/video/BV1AV411v7er",
  "https://www.bilibili.com/video/BV1564y1173Q",

  "https://www.bilibili.com/video/BV1MX4y1N75X",
  "https://www.bilibili.com/video/BV17h411U71w",
  "https://www.bilibili.com/video/BV1ry4y1Y71t",
  "https://www.bilibili.com/video/BV1Sy4y1n7c4",
  "https://www.bilibili.com/video/BV15y4y177uk",
  "https://www.bilibili.com/video/BV1PN411X7QW",
  "https://www.bilibili.com/video/BV1Dp4y1H7iB",
  "https://www.bilibili.com/video/BV1bi4y1P7Eh",
  "https://www.bilibili.com/video/BV1vQ4y1Z7C2",
  "https://www.bilibili.com/video/BV1oU4y1h7Sc",
]

const initConfig = {
  mode: "fixed",
  hidden: true,
  content: {
    link: 寮曟祦[Math.floor(Math.random() * 寮曟祦.length)],
    welcome: ["Hi!"],
    touch: "",
    skin: ["璇讹紝鎯崇湅鐪嬪叾浠栧洟鍛樺悧锛?, "鏇挎崲鍚庡叆鍦烘枃鏈?],
    custom: [
      { "selector": ".comment-form", "text": "Content Tooltip" },
      { "selector": ".home-social a:last-child", "text": "Blog Tooltip" },
      { "selector": ".list .postname", "type": "read" },
      { "selector": ".post-content a, .page-content a, .post a", "type": "link" }
    ],
  },
  night: "toggleNightMode()",
  model: [
    "https://zhiji.bbroot.com/wp-content/themes/zhiji-child/assets/zhiji/live2d/Diana/Diana.model3.json",
    "https://zhiji.bbroot.com/wp-content/themes/zhiji-child/assets/zhiji/live2d/Ava/Ava.model3.json",
  ],
  tips: true,
  onModelLoad: onModelLoad
}

function 鍔犺浇鍦Ｂ峰槈鐒?) {
  pio_reference = new Paul_Pio(initConfig)

  pio_alignment = (window.__zhiji_pio_alignment || "left")

  // Then apply style
  pio_refresh_style()
}

function onModelLoad(model) {
  const container = document.getElementById("pio-container")
  const canvas = document.getElementById("pio")
  const modelNmae = model.internalModel.settings.name
  const coreModel = model.internalModel.coreModel
  const motionManager = model.internalModel.motionManager

  let touchList = [
    {
      text: "鐐瑰嚮灞曠ず鏂囨湰1",
      motion: "Idle"
    },
    {
      text: "鐐瑰嚮灞曠ず鏂囨湰2",
      motion: "Idle"
    }
  ]

  function playAction(action) {
    action.text && pio_reference.modules.render(action.text)
    action.motion && pio_reference.model.motion(action.motion)

    if (action.from && action.to) {
      Object.keys(action.from).forEach(id => {
        const hidePartIndex = coreModel._partIds.indexOf(id)
        TweenLite.to(coreModel._partOpacities, 0.6, { [hidePartIndex]: action.from[id] });
        // coreModel._partOpacities[hidePartIndex] = action.from[id]
      })

      motionManager.once("motionFinish", (data) => {
        Object.keys(action.to).forEach(id => {
          const hidePartIndex = coreModel._partIds.indexOf(id)
          TweenLite.to(coreModel._partOpacities, 0.6, { [hidePartIndex]: action.to[id] });
          // coreModel._partOpacities[hidePartIndex] = action.to[id]
        })
      })
    }
  }

  canvas.onclick = function () {
    if (motionManager.state.currentGroup !== "Idle") return

    const action = pio_reference.modules.rand(touchList)
    playAction(action)
  }

  if (modelNmae === "Diana") {
    container.dataset.model = "Diana"
    initConfig.content.skin[1] = ["鎴戞槸鍚冭揣鎷呭綋 鍢夌劧 Diana~"]
    playAction({ motion: "Tap鎶遍樋鑽?宸︽墜" })

    touchList = [
      {
        text: "鍢夊績绯栧眮鐢ㄦ病鏈?,
        motion: "Tap鐢熸皵 -棰嗙粨"
      },
      {
        text: "鏈変汉鎬ヤ簡锛屼絾鎴戜笉璇存槸璋亊",
        motion: "Tap= =  宸﹁澊铦剁粨"
      },
      {
        text: "鍛滃憸...鍛滃憸鍛?...",
        motion: "Tap鍝?-鐪艰"
      },
      {
        text: "鎯崇劧鐒朵簡娌℃湁鍛€~",
        motion: "Tap瀹崇緸-涓棿鍒樻捣"
      },
      {
        text: "闃胯崏濂借蒋鍛€~",
        motion: "Tap鎶遍樋鑽?宸︽墜"
      },
      {
        text: "涓嶈鍐嶆埑鍟︼紒濂界棐锛?,
        motion: "Tap鎽囧ご- 韬綋"
      },
      {
        text: "鍡峰憸~~~",
        motion: "Tap鑰虫湹-鍙戝崱"
      },
      {
        text: "zzZ銆傘€傘€?,
        motion: "Leave"
      },
      {
        text: "鍝囷紒濂藉悆鐨勶紒",
        motion: "Tap鍙冲ご鍙?
      },
    ]

  } else if (modelNmae === "Ava") {
    container.dataset.model = "Ava"
    initConfig.content.skin[1] = ["鎴戞槸<s>鎷夎儻</s>Gamer鎷呭綋 鍚戞櫄 AvA~"]
    playAction({
      motion: "Tap宸︾溂",
      from: {
        "Part15": 1
      },
      to: {
        "Part15": 0
      }
    })

    touchList = [
      {
        text: "姘存瘝 姘存瘝~ 鍙槸鏅€氱殑鐢熺墿",
        motion: "Tap鍙虫墜"
      },
      {
        text: "鍙埍鐨勯附瀛愰附瀛悀鎴戝枩娆綘~",
        motion: "Tap鑳稿彛椤归摼",
        from: {
          "Part12": 1
        },
        to: {
          "Part12": 0
        }
      },
      {
        text: "濂?..濂藉厔寮熶箣闂村枩娆㈠緢姝ｅ父鍟?,
        motion: "Tap涓棿鍒樻捣",
        from: {
          "Part12": 1
        },
        to: {
          "Part12": 0
        }
      },
      {
        text: "鍟婂晩鍟婏紒鎬庝箞鎺ㄦ祦杈?,
        motion: "Tap鍙崇溂",
        from: {
          "Part16": 1
        },
        to: {
          "Part16": 0
        }
      },
      {
        text: "浣犳€庝箞鑰佹懜鎴戯紝鎴戠殑韬綋鏄笉鏄彲鏈夐瓍鍔?,
        motion: "Tap鍢?
      },
      {
        text: "AAAAAAAAAAvvvvAAA 鍚戞櫄锛?,
        motion: "Tap宸︾溂",
        from: {
          "Part15": 1
        },
        to: {
          "Part15": 0
        }
      }
    ]
    canvas.width = model.width * 1.2
    const hideParts = [
      "Part5", // 鏅?      "neko", // 鍠靛柕鎷?      "game", // 宸︽墜娓告垙鎵嬫焺
      "Part15", // 澧ㄩ暅
      "Part21", // 鍙虫墜灏忚噦
      "Part22", // 宸︽墜鍨備笅
      "Part", // 鍙屾墜鎶辨嫵
      "Part16", // 鎯婅鐗规晥
      "Part12" // 灏忓績蹇?    ]
    const hidePartsIndex = hideParts.map(id => coreModel._partIds.indexOf(id))
    hidePartsIndex.forEach(idx => {
      coreModel._partOpacities[idx] = 0
    })
  }
}


var pio_reference
var __zhijiBoot = 鍔犺浇鍦Ｂ峰槈鐒?
var __zhiji_pio_boot = function(){ __zhijiBoot(); };
if (window.addEventListener) { window.addEventListener("load", __zhiji_pio_boot); } else { window.onload = __zhiji_pio_boot; }
setTimeout(function(){ if (!window.pio_reference) { __zhiji_pio_boot(); } }, 3000);
