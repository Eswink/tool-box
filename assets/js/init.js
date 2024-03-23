jQuery(document).ready(function ($) {
  var globalCaptchaObj // 全局变量用于保存验证码对象
  function insertVerifyText(text) {
    // 判断是否存在 id 为 "verify_text" 的元素
    if ($('#verify_text').length) {
      // 如果存在，则只更改文字内容
      $('#verify_text').text(text)
    } else {
      // 如果不存在，则插入新的 div 元素
      $('#' + comment_verify.buttonId).after(
        '<div id="verify_text" style="color: red;">' + text + '</div>'
      )
    }
  }

  // 初始化 GeeTest
  initGeetest4(
    {
      captchaId: comment_verify.captchaId,
      product: 'bind',
    },
    initGeetestCallback
  )

  // 初始化 GeeTest 回调函数
  function initGeetestCallback(captchaObj) {
    globalCaptchaObj = captchaObj

    globalCaptchaObj.onReady(function () {
      console.warn('验证码加载完毕')
    })

    globalCaptchaObj.onSuccess(function () {
      var captcha = {
        captcha: globalCaptchaObj.getValidate(),
      }

      window.verify_captcha = captcha

      if (window.comment_verify.is_ajax_comment == 0) {
        var form = $('#' + comment_verify.formId)

        // 遍历captcha对象的属性，并添加到隐藏的input元素中
        for (var key in verify_captcha.captcha) {
          if (verify_captcha.captcha.hasOwnProperty(key)) {
            form.append(
              '<input type="hidden" name="captcha[' +
                key +
                ']" value="' +
                verify_captcha.captcha[key] +
                '">'
            )
          }
        }

        HTMLFormElement.prototype.submit.call(
          document.getElementById(comment_verify.formId)
        )
      } else {
        $('#' + comment_verify.formId).submit()
      }

      globalCaptchaObj.reset()
    })

    globalCaptchaObj.onError(function (error) {
      insertVerifyText('验证码加载问题，请刷新页面后重试')
      return false
    })

    globalCaptchaObj.onClose(function () {
      insertVerifyText('需通过验证才能进行评论')
      globalCaptchaObj.reset()
      return false
    })
  }

  // 在点击提交按钮时触发验证码的弹出
  $(document).on('click', '#' + comment_verify.buttonId, function (event) {
    // 在这里添加你的特定条件
    var shouldShowCaptcha = true // 示例条件，根据你的实际需求修改

    if (shouldShowCaptcha) {
      event.preventDefault() // 阻止默认提交行为
      globalCaptchaObj.showCaptcha() // 显示验证码
    }
  })
})
