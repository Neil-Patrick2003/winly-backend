import { cn } from '@/lib/utils';
import type { WinFile } from '@/types';

/**
 * The photos and clips on a post, laid out by how many there are.
 *
 * One fills the width; two split it; three or more tile, with the count of
 * anything past the fourth shown over the last tile rather than growing the
 * card without limit.
 */
export function PostMedia({ files }: { files: WinFile[] }) {
    if (files.length === 0) {
        return null;
    }

    const shown = files.slice(0, 4);
    const overflow = files.length - shown.length;

    return (
        <div
            className={cn(
                'grid gap-0.5 border-y border-border bg-border',
                files.length === 1 && 'grid-cols-1',
                files.length === 2 && 'grid-cols-2',
                files.length >= 3 && 'grid-cols-2',
            )}
        >
            {shown.map((file, index) => {
                const isLast = index === shown.length - 1;
                const spansRow = files.length === 3 && index === 0;

                return (
                    <div
                        key={file.id}
                        className={cn(
                            'relative bg-background',
                            spansRow && 'col-span-2',
                        )}
                    >
                        {file.kind === 'video' ? (
                            <video
                                src={file.url}
                                controls
                                preload="metadata"
                                className="max-h-96 w-full bg-black object-contain"
                            />
                        ) : (
                            <img
                                src={file.url}
                                alt=""
                                loading="lazy"
                                className={cn(
                                    'w-full object-cover',
                                    files.length === 1 ? 'max-h-96' : 'h-44',
                                )}
                            />
                        )}

                        {isLast && overflow > 0 && (
                            <span
                                className="absolute inset-0 flex items-center justify-center bg-black/55 text-lg font-semibold text-white"
                                aria-label={`${overflow} more`}
                            >
                                +{overflow}
                            </span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
