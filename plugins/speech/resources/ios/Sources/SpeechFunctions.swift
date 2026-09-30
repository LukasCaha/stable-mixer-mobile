import AVFoundation
import Foundation

enum SpeechFunctions {
    private static let synthesizer = AVSpeechSynthesizer()

    class Speak: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let text = (parameters["text"] as? String ?? "").trimmingCharacters(in: .whitespacesAndNewlines)
            if text.isEmpty {
                return BridgeResponse.error(code: "empty", message: "Nothing to speak.")
            }

            let utterance = AVSpeechUtterance(string: text)
            utterance.voice = AVSpeechSynthesisVoice(language: AVSpeechSynthesisVoice.currentLanguageCode())
            SpeechFunctions.synthesizer.stopSpeaking(at: .immediate)
            SpeechFunctions.synthesizer.speak(utterance)

            return BridgeResponse.success(data: ["speaking": true])
        }
    }

    class Stop: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            SpeechFunctions.synthesizer.stopSpeaking(at: .immediate)

            return BridgeResponse.success(data: ["speaking": false])
        }
    }
}
