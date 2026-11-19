package dev.pam.contacts

import android.content.Intent
import android.os.ParcelFileDescriptor
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assume.assumeTrue
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class SmsModuleInstrumentedTest {
    @Test
    fun composerIntentTargetsOnlySmsAppsAndCarriesDraft() {
        val intent = SmsModule.smsIntent(
            listOf("+5511999990000", "+5511888880000"),
            "Convite do Zé Chat",
        )

        assertEquals(Intent.ACTION_SENDTO, intent.action)
        assertEquals("smsto", intent.data?.scheme)
        assertEquals(
            "+5511999990000;+5511888880000",
            intent.data?.schemeSpecificPart,
        )
        assertEquals("Convite do Zé Chat", intent.getStringExtra("sms_body"))
    }

    @Test
    fun pluginManifestMakesTheSmsComposerVisible() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        // The shell sees every package; the app only what its merged manifest queries
        // (Android 11+ package visibility; the host manifest no longer queries smsto:).
        val output = ParcelFileDescriptor.AutoCloseInputStream(
            instrumentation.uiAutomation.executeShellCommand(
                "cmd package query-activities --brief -a android.intent.action.SENDTO -d smsto:0",
            ),
        ).bufferedReader().use { it.readText() }
        assumeTrue("no SMS app on this device", output.lines().any { '/' in it })

        val intent = SmsModule.smsIntent(listOf("0"), "")
        assertNotNull(intent.resolveActivity(instrumentation.targetContext.packageManager))
    }

    @Test
    fun emptyBodyDoesNotAddAnSmsExtra() {
        val intent = SmsModule.smsIntent(listOf("+5511999990000"), "")

        assertEquals(false, intent.hasExtra("sms_body"))
    }
}
