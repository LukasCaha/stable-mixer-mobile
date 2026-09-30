package com.stablemixer.speech

import android.content.Context
import android.os.Handler
import android.os.Looper
import android.speech.tts.TextToSpeech
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import java.util.Locale
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

object SpeechFunctions {
    @Volatile private var engine: TextToSpeech? = null
    @Volatile private var ready = false
    @Volatile private var pending: String? = null

    class Speak(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val text = (parameters["text"] as? String)?.trim().orEmpty()
            if (text.isEmpty()) {
                return BridgeResponse.error("empty", "Nothing to speak.")
            }

            val latch = CountDownLatch(1)
            Handler(Looper.getMainLooper()).post {
                speak(activity.applicationContext, text)
                latch.countDown()
            }
            latch.await(2, TimeUnit.SECONDS)

            return BridgeResponse.success(mapOf("speaking" to true))
        }
    }

    class Stop(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val latch = CountDownLatch(1)
            Handler(Looper.getMainLooper()).post {
                pending = null
                engine?.stop()
                latch.countDown()
            }
            latch.await(2, TimeUnit.SECONDS)

            return BridgeResponse.success(mapOf("speaking" to false))
        }
    }

    private fun speak(context: Context, text: String) {
        val current = engine
        if (current == null) {
            pending = text
            engine = TextToSpeech(context) { status ->
                ready = status == TextToSpeech.SUCCESS
                if (ready) {
                    engine?.language = Locale.getDefault()
                    pending?.let { say(it) }
                    pending = null
                }
            }
            return
        }

        if (!ready) {
            pending = text
            return
        }

        say(text)
    }

    private fun say(text: String) {
        engine?.speak(text, TextToSpeech.QUEUE_FLUSH, null, "stable-mixer-answer")
    }
}
