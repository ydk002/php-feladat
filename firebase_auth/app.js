import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
import { 
    getAuth, 
    createUserWithEmailAndPassword, 
    signInWithEmailAndPassword,
    signOut, 
    onAuthStateChanged, 
    updateProfile 
} from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';
import { 
    getFirestore, 
    doc, 
    setDoc, 
    getDoc, 
    serverTimestamp 
} from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js';

// Firebase konfiguráció (helyettesítsd a saját projekt adataiddal szükség esetén)
export const firebaseConfig = {
    apiKey: 'AIzaSyDemoKeyForUmfstPortal_ReplaceMe',
    authDomain: 'umfst-portal.firebaseapp.com',
    projectId: 'umfst-portal',
    storageBucket: 'umfst-portal.appspot.com',
    messagingSenderId: '1234567890',
    appId: '1:1234567890:web:abcdef123456'
};

export const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
export const db = getFirestore(app);

/**
 * Regisztráció
 * A jelszó hash-elését, a sózást és a brute-force védelmet a Firebase Auth végzi.
 */
export async function regisztral(nev, email, jelszo) {
    if (!nev || nev.length < 2 || nev.length > 80) {
        throw new Error('A névnek legalább 2 és legfeljebb 80 karakter hosszúnak kell lennie.');
    }
    const { user } = await createUserWithEmailAndPassword(auth, email, jelszo);
    await updateProfile(user, { displayName: nev });
    await setDoc(doc(db, 'profilok', user.uid), {
        nev,
        email,
        letrehozva: serverTimestamp()
    });
    return user;
}

/**
 * Bejelentkezés
 * Általános hibaüzenet, hogy ne áruljuk el a fiók létezését.
 */
export async function belep(email, jelszo) {
    try {
        const userCredential = await signInWithEmailAndPassword(auth, email, jelszo);
        return userCredential.user;
    } catch (err) {
        // auth/invalid-credential, auth/too-many-requests – általános üzenet a felhasználónak
        throw new Error(err.code === 'auth/too-many-requests'
            ? 'Túl sok próbálkozás, próbáld később.'
            : 'Hibás e-mail vagy jelszó.');
    }
}

/**
 * Kijelentkezés
 */
export const kilep = () => signOut(auth);

/**
 * Figyeli a hitelesítési állapotot és kezeli a felületet
 */
export function figyelAuthAllapot(onAuthChangeCallback) {
    return onAuthStateChanged(auth, async (user) => {
        if (!user) {
            if (onAuthChangeCallback) onAuthChangeCallback(null, null);
            return;
        }

        try {
            const p = await getDoc(doc(db, 'profilok', user.uid));
            const profileData = p.exists() ? p.data() : { nev: user.displayName, email: user.email };
            if (onAuthChangeCallback) onAuthChangeCallback(user, profileData);
        } catch (error) {
            console.error('Hiba a profil betöltésekor:', error);
            if (onAuthChangeCallback) onAuthChangeCallback(user, { nev: user.displayName, email: user.email });
        }
    });
}
